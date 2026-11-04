<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\Goal;
use App\Entity\InvestorProfile;
use App\Entity\RecurringTransaction;
use App\Entity\Transaction;
use App\Entity\User;
use App\Entity\Investment;
use App\Enum\BudgetGroupType;
use App\Enum\TransactionType;
use App\Repository\TransactionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AdvisorService
{
    private const SYSTEM_PROMPT_PATH = 'config/prompts/advisor_system.md';

    /** Nombre de mois d'historique agrege transmis au modele. */
    private const HISTORY_MONTHS = 6;

    /** Plafond de transactions detaillees transmises (garde-fou sur la taille du contexte). */
    private const MAX_TRANSACTIONS = 400;

    private ?string $instructions = null;

    public function __construct(
        private EntityManagerInterface $em,
        private HttpClientInterface $httpClient,
        private TransactionRepository $transactionRepository,
        private string $claudeApiKey,
        private string $projectDir,
    ){}

    /**
     * Instructions statiques du conseiller, lues depuis config/prompts/advisor_system.md.
     * Identiques pour tous les utilisateurs : c'est cette partie qui est mise en cache
     * cote API, donc elle ne doit jamais contenir de donnee variable (date, montants...).
     */
    public function getInstructions(): string
    {
        if ($this->instructions === null) {
            $path = $this->projectDir . '/' . self::SYSTEM_PROMPT_PATH;
            $content = file_get_contents($path);

            if ($content === false) {
                throw new \RuntimeException(sprintf('Prompt systeme introuvable : %s', $path));
            }

            $this->instructions = rtrim($content);
        }

        return $this->instructions;
    }

    public function buildSystemPrompt(User $user): string
    {
        return $this->getInstructions() . "\n\n" . $this->buildUserContext($user);
    }

    /**
     * Donnees financieres du client, recalculees a chaque requete.
     */
    public function buildUserContext(User $user): string
    {
        $profile = $this->em->getRepository(InvestorProfile::class)->findOneBy(['user' => $user]);
        $accounts = $this->em->getRepository(Account::class)->findBy(['user' => $user]);
        $goals = $this->em->getRepository(Goal::class)->findBy(['user' => $user]);
        $investments = $this->em->getRepository(Investment::class)->findBy(['user' => $user]);
        // Meme fenetre que le dashboard (DashboardController::overview).
        $monthStart = (new \DateTime())->modify('first day of this month');
        $monthEnd   = (new \DateTime())->modify('last day of this month');

        $income   = $this->transactionRepository->getMonthlyIncome($user, $monthStart, $monthEnd);
        $expenses = $this->transactionRepository->getMonthlyExpenses($user, $monthStart, $monthEnd);

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

        if ($profile) {
            $profileText = <<<TEXT
                Tranche d'âge : {$profile->getAgeRange()}
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
                Expérience : {$profile->getInvestingExperience()}
                TEXT;
        } else {
            $profileText = 'Profil non renseigné.';
        }

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

        $transactions = $this->transactionRepository->findForPeriod($user, $monthStart, $monthEnd, self::MAX_TRANSACTIONS);
        $recurring    = $this->em->getRepository(RecurringTransaction::class)->findBy(['user' => $user]);

        $transactionsText = $this->formatTransactions($transactions);
        $recurringText    = $this->formatRecurring($recurring);
        $evolutionText    = $this->formatMonthlyEvolution($user);
        $categoryText     = $this->formatCategoryHistory($user);

        $accountsText = '';
        $totalBalance = 0;
        foreach ($accounts as $account) {
            $balance = $account->getBalance() ?? '0';
            $accountsText .= "- {$account->getName()} ({$account->getType()->value}) : solde {$balance} €\n";
            $totalBalance += (float) $balance;
        }
        $context = <<<CONTEXT
            === CONTEXTE DU JOUR ===
            Date d'aujourd'hui : {$today}

            === PROFIL CLIENT ===
            Nom : {$user->getFirstName()} {$user->getLastName()}

            === PROFIL INVESTISSEUR ===
            {$profileText}

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
            Investissements :
            {$investmentText}
            === OBJECTIFS ===
            {$goalsText}
            === COMPTES ===
            Solde total disponible sur les comptes : {$totalBalance} €
            {$accountsText}
            === ÉVOLUTION REVENUS / DÉPENSES (6 DERNIERS MOIS) ===
            {$evolutionText}
            === DÉPENSES PAR CATÉGORIE (6 DERNIERS MOIS, EN €) ===
            {$categoryText}
            === OPÉRATIONS RÉCURRENTES DÉTECTÉES (revenus ET dépenses) ===
            {$recurringText}
            === TRANSACTIONS DÉTAILLÉES DU MOIS EN COURS ===
            {$transactionsText}
            CONTEXT;

        return $context;
    }

    /**
     * @param Transaction[] $transactions
     */
    private function formatTransactions(array $transactions): string
    {
        if ($transactions === []) {
            return 'Aucune transaction enregistrée sur le mois en cours.';
        }

        $labels = [
            TransactionType::INCOME->value   => 'revenu',
            TransactionType::EXPENSE->value  => 'dépense',
            TransactionType::TRANSFER->value => 'virement',
        ];

        $groups = [
            BudgetGroupType::NEEDS->value => 'besoins',
            BudgetGroupType::WANTS->value => 'envies',
        ];

        $lines = [
            'Les lignes « virement » sont des mouvements entre comptes, pas de la consommation.',
            'Elles sont déjà exclues des totaux revenus, dépenses, besoins et envies ci-dessus :',
            'ne les compte jamais comme des dépenses à réduire, et ne les additionne pas aux dépenses.',
            '',
        ];

        foreach ($transactions as $transaction) {
            // Un virement porte une categorie attribuee par defaut par le categoriseur,
            // sans signification : son groupe budgetaire ne veut rien dire. On ne l'affiche pas.
            if ($transaction->getType() === TransactionType::TRANSFER) {
                $classification = 'virement (hors budget)';
            } else {
                $category = $transaction->getCategory()?->getName() ?? 'Non catégorisé';
                $group    = $groups[$transaction->getCategory()?->getBudgetGroup()?->value] ?? null;

                $classification = $group ? "{$category} ({$group})" : $category;
            }

            $lines[] = sprintf(
                '- %s | %s | %s | %s %s €%s',
                $transaction->getDate()?->format('d/m/Y') ?? '??',
                $transaction->getName(),
                $classification,
                $labels[$transaction->getType()?->value] ?? '?',
                number_format(abs((float) $transaction->getAmount()), 2, ',', ' '),
                $transaction->getRecurringTransaction() ? ' | récurrent' : '',
            );
        }

        // Le modele doit savoir qu'il ne voit pas tout, sinon il conclura sur un echantillon.
        if (count($transactions) >= self::MAX_TRANSACTIONS) {
            $lines[] = sprintf(
                'ATTENTION : liste tronquée aux %d premières transactions du mois. Ne conclus pas sur des totaux calculés depuis cette liste, utilise les agrégats ci-dessus.',
                self::MAX_TRANSACTIONS,
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param RecurringTransaction[] $recurring
     */
    private function formatRecurring(array $recurring): string
    {
        if ($recurring === []) {
            return 'Aucune opération récurrente détectée.';
        }

        $labels = [
            TransactionType::INCOME->value   => 'revenu',
            TransactionType::EXPENSE->value  => 'dépense',
            TransactionType::TRANSFER->value => 'virement',
        ];

        $lines = [];
        foreach ($recurring as $item) {
            $lines[] = sprintf(
                '- %s | %s | %s %s € le %s de chaque mois',
                $item->getName(),
                $item->getCategory()?->getName() ?? 'catégorie non renseignée',
                $labels[$item->getType()?->value] ?? '?',
                number_format(abs((float) $item->getAmount()), 2, ',', ' '),
                $item->getDayOfMonth() ?? '?',
            );
        }

        return implode("\n", $lines);
    }

    private function formatMonthlyEvolution(User $user): string
    {
        $evolution = $this->transactionRepository->getMonthlyEvolution(
            $user,
            self::HISTORY_MONTHS,
            $user->getPeriodStartDay() ?? 1,
        );

        $lines = [];
        foreach ($evolution as $month) {
            $balance = $month['income'] - $month['expenses'];
            $lines[] = sprintf(
                '- %s : revenus %s €, dépenses %s €, solde %s%s €',
                $month['month'],
                number_format($month['income'], 2, ',', ' '),
                number_format($month['expenses'], 2, ',', ' '),
                $balance >= 0 ? '+' : '-',
                number_format(abs($balance), 2, ',', ' '),
            );
        }

        return $lines === [] ? 'Historique indisponible.' : implode("\n", $lines);
    }

    /**
     * Matrice categorie x mois : permet de reperer une derive sans transmettre
     * le detail des transactions sur 6 mois.
     */
    private function formatCategoryHistory(User $user): string
    {
        $periods = [];
        for ($i = self::HISTORY_MONTHS - 1; $i >= 0; $i--) {
            $date = new \DateTime("first day of -{$i} months");
            $periods[$date->format('m/Y')] = [
                new \DateTime($date->format('Y-m-01')),
                new \DateTime($date->format('Y-m-t')),
            ];
        }

        $matrix = [];
        foreach ($periods as $label => [$start, $end]) {
            foreach ($this->transactionRepository->getCategoryTotals($user, $start, $end) as $category => $total) {
                $matrix[$category][$label] = $total;
            }
        }

        if ($matrix === []) {
            return 'Aucune dépense catégorisée sur la période.';
        }

        $labels = array_keys($periods);
        $lines  = ['Catégorie | ' . implode(' | ', $labels)];

        foreach ($matrix as $category => $totals) {
            $cells = [];
            foreach ($labels as $label) {
                $cells[] = isset($totals[$label]) ? number_format($totals[$label], 0, ',', ' ') : '0';
            }
            $lines[] = $category . ' | ' . implode(' | ', $cells);
        }

        return implode("\n", $lines);
    }

    public function streamChat(User $user, array $messages): void
    {
        // Une analyse complete depasse largement le max_execution_time de 30 s du php.ini :
        // sans ca, PHP tue le processus en plein stream, apres que le 200 soit parti,
        // et le front recoit une reponse tronquee sans explication.
        set_time_limit(0);

        $instructions = $this->getInstructions();
        $userContext  = $this->buildUserContext($user);

        $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
            'headers' => [
                'x-api-key'         => $this->claudeApiKey,
                'anthropic-version' => '2023-06-01',
                'Content-Type'      => 'application/json',
            ],
            'json' => [
                'model'      => 'claude-sonnet-5',
                'max_tokens' => 32000,
                'stream'     => true,
                'system'     => [
                    // Bloc stable : mis en cache cote API (prefixe identique d'une requete a l'autre).
                    [
                        'type'          => 'text',
                        'text'          => $instructions,
                        'cache_control' => ['type' => 'ephemeral'],
                    ],
                    // Bloc variable : place apres le point de cache, jamais mis en cache.
                    [
                        'type' => 'text',
                        'text' => $userContext,
                    ],
                ],
                'messages'   => $messages,
            ],
            'buffer'       => false,
            // Coupe un flux qui n'avance plus (60 s sans le moindre octet).
            'timeout'      => 60,
            // Plafond absolu, pour ne pas tenir une connexion ouverte indefiniment.
            'max_duration' => 600,
        ]);

        $buffer = '';

        foreach ($this->httpClient->stream($response) as $chunk) {
            $buffer .= $chunk->getContent();

            // Un chunk HTTP peut couper une ligne SSE en deux : on ne traite que les
            // lignes completes et on garde le fragment restant pour le tour suivant.
            while (($newline = strpos($buffer, "\n")) !== false) {
                $line   = rtrim(substr($buffer, 0, $newline), "\r");
                $buffer = substr($buffer, $newline + 1);

                if (!str_starts_with($line, 'data: ')) {
                    continue;
                }

                $data = json_decode(substr($line, 6), true);

                if (!is_array($data)) {
                    continue;
                }

                $this->forwardEvent($data);
            }
        }

        $this->send(['done' => true]);
    }

    /**
     * Traduit un evenement SSE de l'API Anthropic en evenement pour le front.
     */
    private function forwardEvent(array $data): void
    {
        $type = $data['type'] ?? '';

        if ($type === 'content_block_delta') {
            // Seuls les deltas de texte nous interessent (un delta de thinking n'a pas de 'text').
            if (($data['delta']['type'] ?? '') === 'text_delta') {
                $this->send(['text' => $data['delta']['text'] ?? '']);
            }

            return;
        }

        if ($type === 'message_delta') {
            $stopReason = $data['delta']['stop_reason'] ?? null;

            // end_turn = reponse complete, rien a signaler. Tout le reste est anormal :
            // max_tokens (reponse coupee), refusal, pause_turn...
            if ($stopReason !== null && $stopReason !== 'end_turn') {
                $this->send(['stopReason' => $stopReason]);
            }

            return;
        }

        if ($type === 'error') {
            $this->send(['error' => $data['error']['message'] ?? 'Erreur inconnue de l\'API.']);
        }
    }

    private function send(array $payload): void
    {
        echo 'data: ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . "\n\n";

        // StreamedResponse n'ouvre pas de buffer de sortie : ob_flush() sans buffer actif emet une notice.
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}