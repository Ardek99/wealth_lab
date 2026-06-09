<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\Goal;
use App\Entity\InvestorProfile;
use App\Entity\User;
use App\Entity\Investment;
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
        foreach ($investments as $investment) {
            $investmentText .= "- {$investment->getName()} ({$investment->getType()->value}) : valeur actuelle {$investment->getCurrentValue()} €, plus/moins-value {$investment->getGainLoss()} € ({$investment->getGainLossPercent()} %)\n";
        }

        $goalsText = '';
        foreach ($goals as $goal) {
            $goalsText .= "- {$goal->getName()} : {$goal->getCurrentAmount()} € / {$goal->getTargetAmount()} € (échéance {$goal->getTargetDate()->format('d/m/Y')})\n";
        }

        $accountsText = '';
        foreach ($accounts as $account) {
            $accountsText .= "- {$account->getName()} ({$account->getType()->value})\n";
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

            La date d'aujourd'hui est le {$today}.

            === PROFIL CLIENT ===
            Nom : {$user->getFirstName()} {$user->getLastName()}
            === PROFIL INVESTISSEUR ===
            Profil : {$profileText}
            === SITUATION DU MOIS ===
            Revenus : {$income} €
            Dépenses : {$expenses} €
            === PORTEFEUILLE ===
            Investissements : {$investmentText}
            === OBJECTIFS ===
            Objectifs : {$goalsText}
            === COMPTES ===
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
                'model'      => 'claude-opus-4-8',
                'max_tokens' => 1500,
                'stream'     => true,
                'system'     => $systemPrompt,
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