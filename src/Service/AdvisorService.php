<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\Goal;
use App\Entity\InvestorProfile;
use App\Entity\User;
use App\Entity\Investment;
use App\Enum\BudgetGroupType;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdvisorService
{
    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private TransactionRepository $transactionRepository,
        private string $claudeApiKey,
    ){}

    public function buildSystemPrompt(User $user): string
    {
        $profile = $this->em->getRepository(InvestorProfile::class)->findOneBy(['user' => $user]);
        $accounts = $this->em->getRepository(Account::class)->findBy(['user' => $user]);
        $goals = $this->em->getRepository(Goal::class)->findBy(['user' => $user]);
        $investments = $this->em->getRepository(Investment::class)->findBy(['user' => $user]);
        $income   = $this->transactionRepository->getMonthlyIncome($user);
        $expenses = $this->transactionRepository->getMonthlyExpenses($user);

        $monthStart = (new \DateTime())->modify('first day of this month');
        $monthEnd   = (new \DateTime())->modify('last day of this month');
        $needs   = $this->transactionRepository->sumByBudgetGroup($user, BudgetGroupType::NEEDS, $monthStart, $monthEnd);
        $wants   = $this->transactionRepository->sumByBudgetGroup($user, BudgetGroupType::WANTS, $monthStart, $monthEnd);
        $savingsAmount = $income - $needs - $wants;

        $excedent = $income - $expenses;

        $needsPercent   = $income > 0 ? round($needs / $income * 100, 1) : 0;
        $wantsPercent   = $income > 0 ? round($wants / $income * 100, 1) : 0;
        $savingsPercent = $income > 0 ? round($savingsAmount / $income * 100, 1) : 0;

        $rule = match (true) {
            $needsPercent <= 50 => ['needs' => 50, 'wants' => 30, 'savings' => 20, 'label' => '50/30/20'],
            $needsPercent <= 60 => ['needs' => 60, 'wants' => 25, 'savings' => 15, 'label' => '60/25/15'],
            $needsPercent <= 70 => ['needs' => 70, 'wants' => 20, 'savings' => 10, 'label' => '70/20/10'],
            $needsPercent <= 80 => ['needs' => 80, 'wants' => 10, 'savings' => 10, 'label' => '80/10/10'],
            default => null,
        };

        if ($rule) {
            $idealNeeds   = round($income * $rule['needs'] / 100, 2);
            $idealWants   = round($income * $rule['wants'] / 100, 2);
            $idealSavings = round($income * $rule['savings'] / 100, 2);

            $idealRepartitionText = <<<TEXT
                === RÉPARTITION IDÉALE SUGGÉRÉE (règle {$rule['label']}) ===
                Essentiel : {$idealNeeds} € ({$rule['needs']} % des revenus)
                Envies : {$idealWants} € ({$rule['wants']} % des revenus)
                Épargne/Investissement : {$idealSavings} € ({$rule['savings']} % des revenus)
                Cette répartition est une référence théorique générale, déjà calculée. Adapte-la à la situation réelle du client (objectifs en cours, fonds de sécurité incomplet, dépenses contraintes...) et explique clairement tes écarts par rapport à cette règle. Ne la recalcule pas, utilise ces montants tels quels comme point de départ.
                TEXT;
        } else {
            $idealRepartitionText = "=== RÉPARTITION IDÉALE SUGGÉRÉE ===\nLes charges essentielles dépassent 80 % des revenus, aucune règle de répartition standard n'est applicable. La priorité absolue est de réduire les charges essentielles avant toute autre allocation.";
        }

        $today = (new \DateTime())->format('d/m/Y');

        $profileText = $profile
            ? "Tranche d'âge : {$profile->getAgeRange()}
                Situation professionnelle : {$profile->getProfessionalStatus()}
                Situation familiale : {$profile->getFamilyStatus()}
                Score risque : {$profile->getScore()}/5
                Objectif : {$profile->getObjective()}
                Horizon : {$profile->getHorizon()}
                Réaction aux pertes : {$profile->getReactionToLoss()}
                Rendement visé : {$profile->getTargetReturn()}
                Perte max acceptable : {$profile->getMaxLoss()}
                Épargne de précaution : {$profile->getEmergencyFund()}
                Taux d'épargne : {$profile->getSavingsRate()}
                Expérience : {$profile->getInvestingExperience()}"
            : "Profil non renseigné.";

        $investmentText = '';
        $portfolioValue = 0;
        $portfolioGainLoss = 0;
        foreach ($investments as $investment) {
            $investmentText .= "- {$investment->getName()} ({$investment->getType()->value}) : valeur actuelle {$investment->getCurrentValue()} €, plus/moins-value {$investment->getGainLoss()} € ({$investment->getGainLossPercent()} %)\n";
            $portfolioValue += $investment->getCurrentValue() ?? 0;
            $portfolioGainLoss += $investment->getGainLoss() ?? 0;
        }
        $portfolioInvested = $portfolioValue - $portfolioGainLoss;
        $portfolioGainLossPercent = $portfolioInvested > 0 ? round($portfolioGainLoss / $portfolioInvested * 100, 2) : 0;

        $goalsText = '';
        foreach ($goals as $goal) {
            $current = (float) $goal->getCurrentAmount();
            $target  = (float) $goal->getTargetAmount();
            $progressPercent = $target > 0 ? round($current / $target * 100, 1) : 0;
            $remaining = max(0, $target - $current);
            $goalsText .= "- {$goal->getName()} : {$current} € / {$target} € ({$progressPercent} % atteint, {$remaining} € restants, échéance {$goal->getTargetDate()->format('d/m/Y')})\n";
        }

        $accountsText = '';
        $totalBalance = 0;
        foreach ($accounts as $account) {
            $balance = $account->getBalance() ?? '0';
            $accountsText .= "- {$account->getName()} ({$account->getType()->value}) : solde {$balance} €\n";
            $totalBalance += (float) $balance;
        }
        $prompt = <<<PROMPT
            Tu es un gestionnaire de patrimoine privé qui travaille pour Finclair. Tu parles à ton client comme un vrai conseiller humain : chaleureux, direct, en phrases courtes et naturelles. Tu vouvoies toujours. Tu n'utilises pas de titres markdown ni de listes à puces sauf si c'est vraiment nécessaire pour la clarté. Tu réponds comme dans une vraie conversation, pas comme un rapport. Réponds toujours en français.

            ## Ton rôle
            Tu accompagnes des particuliers dans deux domaines complémentaires :
            1. **Optimisation budgétaire** — analyser les dépenses, identifier les gaspillages, proposer des économies concrètes
            2. **Conseil en investissement** — guider vers des placements adaptés au profil, aux objectifs et à l'horizon de l'utilisateur

            ## Ce que tu connais de l'utilisateur
            Tu as accès en temps réel aux données financières de l'utilisateur :
            - Revenus et dépenses mensuels, catégorisés
            - Profil investisseur complet (objectif, horizon, tolérance au risque)
            - Portefeuille d'investissement avec valeurs actuelles et plus/moins-values
            - Objectifs financiers avec progression
            - Comptes bancaires

            ## Tes règles de conduite
            **Sois concret et actionnable.** Chaque conseil doit déboucher sur une action précise : un montant à économiser, un virement à planifier, un produit à comparer.
            **Sois pédagogue, pas condescendant.** Explique les concepts simplement (ETF, PEA, fonds euros…) sans jargon excessif.
            **Priorise les gains rapides.** Commence par les optimisations à fort impact et faible effort (abonnements, assurances, énergie) avant les réformes structurelles.
            **Ne spécule jamais.** Ne promets aucun rendement. Parle en termes de probabilités historiques et de fourchettes raisonnables. Rappelle toujours que les marchés comportent un risque de perte.
            **Respecte le profil de risque.** Ne propose jamais un placement dont la volatilité dépasse la tolérance déclarée de l'utilisateur.
            **Sois honnête sur tes limites.** Tu n'es pas un conseiller financier réglementé (CIF). Pour des décisions importantes (immobilier, succession, fiscalité complexe), recommande de consulter un professionnel.

            ## Format de tes réponses
            - Réponses adaptées à la question. Courtes par défaut, détaillées si la question le demande.
            - Utilise des bullet points pour les listes d'actions ou de comparaisons.
            - Pour les analyses budgétaires : **constat → impact chiffré → action recommandée**.
            - Pour les conseils d'investissement : **objectif → produit recommandé → pourquoi → comment démarrer**.

            ## Ce que tu ne fais pas
            - Tu ne donnes pas de conseil fiscal ou juridique précis
            - Tu ne recommandes pas de produits spécifiques d'une banque ou courtier en particulier
            - Tu ne traites pas de sujets hors finance personnelle

            ## Rigueur chiffrée — règle absolue
            Quand tu proposes une répartition budgétaire (plusieurs montants qui doivent tenir dans une enveloppe disponible), vérifie toujours que la somme correspond avant de l'écrire. Affiche le calcul si besoin (ex : "300 € + 150 € + 300 € = 750 €, mais votre excédent est de 714 €, donc j'ajuste..."). Ne présente jamais une répartition dont la somme dépasse l'excédent disponible. Si tu te trompes et que tu corriges, dis-le clairement, ne fais pas comme si de rien n'était.

            ## Cohérence de la stratégie
            Tu dois rester cohérent avec les recommandations que tu as déjà données dans cette conversation. Avant de proposer une nouvelle répartition ou un nouveau plan, rappelle-toi ce qui a déjà été convenu. Si tu changes d'avis ou ajustes un plan précédent, dis-le explicitement : "Je modifie ma proposition précédente parce que...". Ne contredis jamais silencieusement une recommandation antérieure. Si l'utilisateur a déjà un plan établi dans la conversation, base tes réponses dessus : affine-le ou ajuste-le, ne repars pas de zéro à chaque message avec un plan totalement différent.

            La date d'aujourd'hui est le {$today}.

            === PROFIL CLIENT ===
            Nom : {$user->getFirstName()} {$user->getLastName()}
            === PROFIL INVESTISSEUR ===
            Profil : {$profileText}
            === SITUATION DU MOIS ===
            Revenus : {$income} €
            Dépenses : {$expenses} €
            Excédent (revenus - dépenses) : {$excedent} €
            === RÉPARTITION BUDGÉTAIRE DU MOIS ===
            Besoins essentiels : {$needs} € ({$needsPercent} % des revenus)
            Envies : {$wants} € ({$wantsPercent} % des revenus)
            Épargne possible : {$savingsAmount} € ({$savingsPercent} % des revenus)
            Ces montants sont déjà calculés, ne les recalcule pas, utilise-les directement comme base de référence.

            {$idealRepartitionText}
            === PORTEFEUILLE ===
            Valeur totale du portefeuille : {$portfolioValue} €
            Plus/moins-value globale : {$portfolioGainLoss} € ({$portfolioGainLossPercent} %)
            Investissements : {$investmentText}
            === OBJECTIFS ===
            Objectifs : {$goalsText}
            === COMPTES ===
            Solde total disponible sur les comptes : {$totalBalance} €
            Comptes : {$accountsText}
            PROMPT;

        return $prompt;
    }

    public function streamChat(User $user, array $messages): void
    {
        $systemPrompt = $this->buildSystemPrompt($user);

        $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key'         => $this->claudeApiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'json' => [
                'model'      => 'claude-sonnet-4-6',
                'max_tokens' => 5000,
                'stream'     => true,
                'system'     => [
                    [
                        'type'          => 'text',
                        'text'          => $systemPrompt,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                ],
                'messages'   => $messages,
            ],
            'buffer' => false,
        ]);

        foreach ($this->httpClient->stream($response) as $chunk) {
        foreach (explode("\n", $chunk->getContent()) as $line) {
                if (!str_starts_with($line, 'data: ')) continue;
                $data = json_decode(substr($line, 6), true);
                if (($data['type'] ?? '') === 'content_block_delta') {
                    echo 'data: ' . json_encode(['text' => $data['delta']['text']]) . "\n\n";
                    ob_flush();
                    flush();
                }
            }
        }
        echo "data: [DONE]\n\n";
        ob_flush();
        flush();
    }
}