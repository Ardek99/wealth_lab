<?php

namespace App\Service;

use App\Entity\Category;
use App\Enum\CategoryType;
use App\Repository\CategoryRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Picks a real category for a synced transaction instead of dumping
 * everything into "Autres revenus"/"Autres dépenses". Cheapest and most
 * trusted signal first:
 *
 *   1. Native bank category (only for modules confirmed to provide a real
 *      one — see woob-sync's TRUSTED_NATIVE_CATEGORY_MODULES)
 *   2. Keyword match on the transaction label
 *   3. Claude, batched once per account per sync, for whatever's left
 *   4. "Autres revenus"/"Autres dépenses" by sign, same as before
 */
class TransactionCategorizerService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CategoryRepository $categoryRepository,
        private LoggerInterface $logger,
        private string $claudeApiKey,
    ) {}

    /** Boursorama's own category label -> our category name. Deliberately
     * excludes "Virements émis/reçus" and "Non catégorisé" — neither says
     * anything about what the transaction actually is, so let those fall
     * through to keyword matching / Claude instead. */
    private const NATIVE_CATEGORY_MAP = [
        'Alimentation' => 'Alimentation',
        'Restaurants, bars, discothèques…' => 'Restaurants',
        'Transports longue distance (avions, trains…)' => 'Transport',
        'Péages' => 'Transport',
        'Voyages & transports - autres' => 'Voyages',
        'Equipements sportifs et artistiques' => 'Shopping',
        'Club / association (sport, hobby, art…)' => 'Loisirs',
        'Divertissement - culture (ciné, théâtre, concerts…)' => 'Loisirs',
        'Mobilier, électroménager, décoration…' => 'Shopping',
        'Médecins et frais médicaux' => 'Santé',
        'Remboursements frais de santé' => 'Remboursements',
        'Vie quotidienne' => 'Shopping',
    ];

    /** Keyword (matched case-insensitively, substring) -> category name.
     * Covers Crédit Agricole (no native category at all) and anything
     * Boursorama didn't map above. Not exhaustive — extend as needed. */
    private const KEYWORD_CATEGORY_MAP = [
        // Alimentation
        'CARREFOUR' => 'Alimentation', 'LECLERC' => 'Alimentation', 'MONOPRIX' => 'Alimentation',
        'AUCHAN' => 'Alimentation', 'LIDL' => 'Alimentation', 'INTERMARCHE' => 'Alimentation',
        'CASINO' => 'Alimentation', 'FRANPRIX' => 'Alimentation', 'ALDI' => 'Alimentation',
        'BIOCOOP' => 'Alimentation', 'PICARD' => 'Alimentation', 'MERCADONA' => 'Alimentation',
        // Restaurants
        'MCDONALD' => 'Restaurants', 'BURGER KING' => 'Restaurants', 'RESTAURANT' => 'Restaurants',
        'UBER EATS' => 'Restaurants', 'DELIVEROO' => 'Restaurants', 'PIZZA' => 'Restaurants',
        // Transport
        'SNCF' => 'Transport', 'RATP' => 'Transport', 'UBER' => 'Transport', 'TOTAL' => 'Transport',
        'ESSO' => 'Transport', 'SHELL' => 'Transport', 'PARKING' => 'Transport', 'AUTOROUTE' => 'Transport',
        'APRR' => 'Transport', 'BLABLACAR' => 'Transport',
        // Logement
        'LOYER' => 'Logement',
        // Électricité, Eau, Gaz
        'EDF' => 'Électricité, Eau, Gaz', 'ENGIE' => 'Électricité, Eau, Gaz',
        'TOTALENERGIES' => 'Électricité, Eau, Gaz', 'VEOLIA' => 'Électricité, Eau, Gaz',
        'SUEZ' => 'Électricité, Eau, Gaz', 'LA BELLENERGIE' => 'Électricité, Eau, Gaz', 'ENEDIS' => 'Électricité, Eau, Gaz',
        // Téléphone, Internet
        'ORANGE' => 'Téléphone, Internet', 'SFR' => 'Téléphone, Internet',
        'BOUYGUES' => 'Téléphone, Internet', 'FREE MOBILE' => 'Téléphone, Internet', 'SOSH' => 'Téléphone, Internet',
        // Assurances
        'ASSURANCE' => 'Assurances', 'MAAF' => 'Assurances', 'MAIF' => 'Assurances',
        'AXA' => 'Assurances', 'ALLIANZ' => 'Assurances', 'GMF' => 'Assurances', 'MATMUT' => 'Assurances',
        // Abonnements
        'NETFLIX' => 'Abonnements', 'SPOTIFY' => 'Abonnements', 'DISNEY' => 'Abonnements',
        'AMAZON PRIME' => 'Abonnements', 'DEEZER' => 'Abonnements', 'APPLE.COM' => 'Abonnements',
        'CANAL+' => 'Abonnements', 'OCS' => 'Abonnements', 'HBO' => 'Abonnements',
        // Santé
        'PHARMACIE' => 'Santé', 'DOCTEUR' => 'Santé', 'DENTISTE' => 'Santé',
        // Shopping
        'AMAZON' => 'Shopping', 'FNAC' => 'Shopping', 'DECATHLON' => 'Shopping',
        'ZARA' => 'Shopping', 'CDISCOUNT' => 'Shopping',
        // Voyages
        'AIRBNB' => 'Voyages', 'BOOKING.COM' => 'Voyages', 'AIRFRANCE' => 'Voyages',
        'RYANAIR' => 'Voyages', 'GETYOURGUIDE' => 'Voyages',
        // Frais bancaires
        'COTISATION' => 'Frais bancaires', 'COMMISSION' => 'Frais bancaires',
        // Crédits, Prêts
        'PRET ETUDIANT' => 'Crédits, Prêts', 'PRET IMMOBILIER' => 'Crédits, Prêts',
        // Salaire
        'SALAIRE' => 'Salaire', 'PAIE' => 'Salaire',
        // Allocations
        'CAF' => 'Allocations', 'POLE EMPLOI' => 'Allocations', 'FRANCE TRAVAIL' => 'Allocations',
        // Remboursements
        'CPAM' => 'Remboursements', 'REMBOURSEMENT' => 'Remboursements', 'MUTUELLE' => 'Remboursements',
        // Dividendes
        'DIVIDENDE' => 'Dividendes',
    ];

    /**
     * @param array<int, array{label: string, nativeCategory: ?string, amount: float}> $items
     * @return array<int, Category> same order/length as $items
     */
    public function categorizeBatch(array $items): array
    {
        $results = [];
        $unresolved = [];

        foreach ($items as $i => $item) {
            $category = $this->matchNativeCategory($item['nativeCategory'], $item['amount'])
                ?? $this->matchKeyword($item['label'], $item['amount']);

            if ($category !== null) {
                $results[$i] = $category;
            } else {
                $unresolved[$i] = $item;
            }
        }

        if ($unresolved !== [] && $this->claudeApiKey !== '') {
            foreach ($this->matchWithClaude($unresolved) as $i => $category) {
                $results[$i] = $category;
            }
        }

        foreach ($items as $i => $item) {
            $results[$i] ??= $this->fallbackCategory($item['amount']);
        }

        ksort($results);

        return $results;
    }

    private function matchNativeCategory(?string $nativeCategory, float $amount): ?Category
    {
        if ($nativeCategory === null) {
            return null;
        }

        $name = self::NATIVE_CATEGORY_MAP[$nativeCategory] ?? null;

        return $name !== null ? $this->findByNameForSign($name, $amount) : null;
    }

    private function matchKeyword(string $label, float $amount): ?Category
    {
        $upperLabel = mb_strtoupper($label);

        foreach (self::KEYWORD_CATEGORY_MAP as $keyword => $categoryName) {
            if (str_contains($upperLabel, $keyword)) {
                $category = $this->findByNameForSign($categoryName, $amount);
                if ($category !== null) {
                    return $category;
                }
            }
        }

        return null;
    }

    /** Looks up a category by name, but only returns it if its type actually
     * matches the transaction's sign — a keyword/native mapping table entry
     * pointing at the wrong sign's category (a bug, not real data) shouldn't
     * silently apply. */
    private function findByNameForSign(string $name, float $amount): ?Category
    {
        $category = $this->categoryRepository->findOneBy(['name' => $name]);
        if ($category === null) {
            return null;
        }

        $expectedType = $amount >= 0 ? CategoryType::INCOME : CategoryType::EXPENSE;

        return $category->getType() === $expectedType ? $category : null;
    }

    private function fallbackCategory(float $amount): ?Category
    {
        return $this->categoryRepository->findOneBy([
            'name' => $amount >= 0 ? 'Autres revenus' : 'Autres dépenses',
        ]);
    }

    /**
     * @param array<int, array{label: string, nativeCategory: ?string, amount: float}> $unresolved
     * @return array<int, Category>
     */
    private function matchWithClaude(array $unresolved): array
    {
        $incomeNames = array_map(fn (Category $c) => $c->getName(), $this->categoryRepository->findBy(['type' => CategoryType::INCOME]));
        $expenseNames = array_map(fn (Category $c) => $c->getName(), $this->categoryRepository->findBy(['type' => CategoryType::EXPENSE]));

        // Listed once each (not repeated per line) and the response format is
        // a flat {index: categoryName} map rather than an array of {index,
        // category} objects — both cut token usage substantially, since a
        // real sync can easily have 100+ unresolved transactions in one
        // account and a verbose format risks the response getting cut off
        // before every item is classified (silently losing the tail of the
        // batch to the Autres fallback).
        $lines = [];
        foreach ($unresolved as $i => $item) {
            $lines[] = sprintf('%d: "%s" (%s)', $i, $item['label'], $item['amount'] >= 0 ? 'income' : 'expense');
        }

        $prompt = "Classify each French bank transaction below into one category.\n"
            . "Income categories: " . implode(', ', $incomeNames) . "\n"
            . "Expense categories: " . implode(', ', $expenseNames) . "\n"
            . "Use the income list for transactions marked (income), the expense list for (expense).\n"
            . "Reply with ONLY a single-line JSON object mapping each index to its category name, "
            . "e.g. {\"0\":\"Restaurants\",\"1\":\"Salaire\"}. No other text, no markdown.\n\n"
            . implode("\n", $lines);

        // Generous, scaled headroom — a truncated response silently loses
        // whatever didn't fit, which is worse than a slightly wasteful cap.
        $maxTokens = min(8000, max(2048, count($unresolved) * 20));

        try {
            $response = $this->httpClient->request('POST', 'https://api.anthropic.com/v1/messages', [
                'headers' => [
                    'x-api-key' => $this->claudeApiKey,
                    'anthropic-version' => '2023-06-01',
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'claude-sonnet-4-6',
                    'max_tokens' => $maxTokens,
                    'messages' => [['role' => 'user', 'content' => $prompt]],
                ],
            ]);

            $data = $response->toArray();
            $stopReason = $data['stop_reason'] ?? null;
            $text = $data['content'][0]['text'] ?? '';
            $assignments = json_decode($text, true);

            if ($stopReason === 'max_tokens') {
                $this->logger->warning('TransactionCategorizerService: Claude response truncated (max_tokens={max_tokens}), partial results only', ['max_tokens' => $maxTokens, 'itemCount' => count($unresolved)]);
            }
            if (!is_array($assignments)) {
                $this->logger->warning('TransactionCategorizerService: Claude response was not valid JSON', ['response' => $text]);
                return [];
            }
        } catch (\Throwable $e) {
            $this->logger->warning('TransactionCategorizerService: Claude call failed', ['exception' => $e->getMessage()]);
            return [];
        }

        $results = [];
        foreach ($assignments as $index => $categoryName) {
            $i = (int) $index;
            if (!isset($unresolved[$i]) || !is_string($categoryName)) {
                continue;
            }
            $category = $this->findByNameForSign($categoryName, $unresolved[$i]['amount']);
            if ($category !== null) {
                $results[$i] = $category;
            }
        }

        return $results;
    }
}
