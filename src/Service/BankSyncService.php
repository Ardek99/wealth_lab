<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\BankConnection;
use App\Entity\Investment;
use App\Entity\InvestmentOrder;
use App\Entity\Transaction;
use App\Entity\User;
use App\Enum\AccountType;
use App\Enum\BankConnectionStatus;
use App\Enum\InvestmentOrderDirection;
use App\Enum\InvestmentType;
use App\Enum\TransactionType;
use App\Repository\InvestmentOrderRepository;
use App\Repository\InvestmentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BankSyncService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CredentialEncryptionService $encryptionService,
        private EntityManagerInterface $em,
        private TransactionCategorizerService $categorizer,
        private InvestmentRepository $investmentRepository,
        private InvestmentOrderRepository $investmentOrderRepository,
        private RecurringTransactionDetector $recurringDetector,
        private string $syncUrl,
    ) {}

    public function sync(BankConnection $connection): void
    {
        set_time_limit(600);

        $config = $this->encryptionService->decrypt($connection->getCredentials());

        $response = $this->httpClient->request('POST', $this->syncUrl . '/sync', [
            'json' => [
                'module' => $connection->getModule(),
                'config' => $config,
                'since' => (new \DateTime('-3 months'))->format('Y-m-d'),
            ],
            'timeout' => 600,
        ]);
        $data = $response->toArray(false);

        // Cas StatusResponse : pas de sync possible
        if (isset($data['status'])) {
            $connection->setStatus(match ($data['status']) {
                'invalid_credentials' => BankConnectionStatus::INVALID_CREDENTIALS,
                '2fa_required', 'captcha_required', 'action_needed' => BankConnectionStatus::ACTION_NEEDED,
                default => BankConnectionStatus::ERROR,
            });
            $connection->setLastErrorMessage($data['message']);
            $this->em->flush();
            return;
        }

        $user = $connection->getUser();
        $accountRepo = $this->em->getRepository(Account::class);
        $transactionRepo = $this->em->getRepository(Transaction::class);

        foreach ($data['accounts'] as $accountData) {
            $account = $accountRepo->findOneBy([
                'bankConnection' => $connection,
                'externalId' => $accountData['externalId'],
            ]) ?? (new Account())->setUser($user)
                ->setBankConnection($connection)
                ->setExternalId($accountData['externalId']);

            $account->setName($accountData['label']);
            $account->setBalance((string) $accountData['balance']);
            $account->setType(AccountType::from($accountData['type']));
            $this->em->persist($account);

            $newTransactions = array_values(array_filter(
                $accountData['transactions'],
                fn (array $trData) => $transactionRepo->findOneBy([
                    'account' => $account,
                    'externalId' => $trData['externalId'],
                ]) === null,
            ));

            $categories = $this->categorizer->categorizeBatch(array_map(
                fn (array $trData) => [
                    'label' => $trData['label'],
                    'nativeCategory' => $trData['nativeCategory'] ?? null,
                    'amount' => (float) $trData['amount'],
                ],
                $newTransactions,
            ));

            foreach ($newTransactions as $i => $trData) {
                $amount = (float) $trData['amount'];
                $type = match (true) {
                    // A transfer between the user's own accounts (e.g. checking
                    // -> savings, or checking at one bank -> checking at another)
                    // isn't real income or expense — counting it as either
                    // double-counts money that never left the user's total
                    // balance, inflating both sides of the stats. French banks
                    // label these transfers with the account holder's own name
                    // as sender/recipient, which is what we key off — a generic
                    // "starts with VIR" check would also catch real P2P payments
                    // (rent, splitting a bill, a reimbursement) that happen to
                    // use the same transfer rail.
                    $this->isSelfTransfer($trData['label'], $user) => TransactionType::TRANSFER,
                    $amount >= 0 => TransactionType::INCOME,
                    default => TransactionType::EXPENSE,
                };

                $transaction = (new Transaction())
                    ->setAccount($account)
                    ->setExternalId($trData['externalId'])
                    ->setName($trData['label'])
                    ->setAmount((string) $amount)
                    ->setDate(new \DateTime($trData['date']))
                    ->setType($type)
                    ->setCategory($categories[$i]);

                $this->em->persist($transaction);
            }

            $investmentsByTicker = [];
            foreach ($accountData['investments'] ?? [] as $invData) {
                $investment = $this->investmentRepository->findOneBy([
                    'account' => $account,
                    'externalId' => $invData['externalId'],
                ]) ?? (new Investment())->setUser($user)
                    ->setAccount($account)
                    ->setExternalId($invData['externalId'])
                    ->setPurchaseDate($this->findLatestBuyDate($accountData['marketOrders'] ?? [], $invData['ticker']) ?? new \DateTime());

                $investment->setName($invData['label']);
                $investment->setType($this->guessInvestmentType($invData['label']));
                $investment->setTicker($invData['ticker']);
                $investment->setQuantity((string) $invData['quantity']);
                $investment->setPurchasePrice((string) $invData['unitPrice']);
                if ($invData['currentPrice'] !== null) {
                    $investment->setCurrentPrice((string) $invData['currentPrice']);
                    $investment->setCurrentPriceUpdatedAt(new \DateTime());
                }
                $this->em->persist($investment);

                if ($invData['ticker'] !== null) {
                    $investmentsByTicker[$invData['ticker']] = $investment;
                }
            }

            foreach ($accountData['marketOrders'] ?? [] as $orderData) {
                $investment = $investmentsByTicker[$orderData['ticker']] ?? null;
                if (!$investment) {
                    continue; // no matching holding for this order, skip
                }

                $exists = $this->investmentOrderRepository->findOneBy([
                    'investment' => $investment,
                    'externalId' => $orderData['externalId'],
                ]);
                if ($exists) {
                    continue; // déjà importé
                }

                $order = (new InvestmentOrder())
                    ->setInvestment($investment)
                    ->setDirection(InvestmentOrderDirection::from($orderData['direction']))
                    ->setQuantity((string) $orderData['quantity'])
                    ->setUnitPrice((string) $orderData['unitPrice'])
                    ->setDate(new \DateTime($orderData['date']))
                    ->setExternalId($orderData['externalId']);

                $this->em->persist($order);
            }
        }

        $connection->setStatus(BankConnectionStatus::ACTIVE);
        $connection->setLastErrorMessage(null);
        $connection->setLastSyncDate(new \DateTime());
        $this->em->flush();

        $this->recurringDetector->detectForUser($user);
    }

    /**
     * An aggregated holding has no single purchase date (it's a weighted-average
     * cost basis, possibly built from several orders over time). Best-effort
     * substitute: the date of the most recent executed BUY order for the same
     * ticker, if one was found in this sync's order history.
     *
     * @param array<int, array{ticker: ?string, direction: string, date: string}> $marketOrders
     */
    private function findLatestBuyDate(array $marketOrders, ?string $ticker): ?\DateTime
    {
        if ($ticker === null) {
            return null;
        }

        $latest = null;
        foreach ($marketOrders as $orderData) {
            if ($orderData['ticker'] !== $ticker || $orderData['direction'] !== 'buy') {
                continue;
            }
            $date = new \DateTime($orderData['date']);
            if ($latest === null || $date > $latest) {
                $latest = $date;
            }
        }

        return $latest;
    }

    /**
     * French banks label a transfer with the account holder's own name as
     * sender/recipient (e.g. "VIR INST TOURNIER TOM"), whether it's an
     * instant transfer between the user's own accounts or, incidentally, a
     * payment to/from someone who happens to share initials — good enough as
     * a heuristic since first AND last name both have to match. Requires the
     * user's profile to have their real name set; if it doesn't, nothing is
     * ever classified as a transfer (falls back to income/expense as before).
     */
    private function isSelfTransfer(string $label, User $user): bool
    {
        $firstName = $this->normalizeForMatch($user->getFirstName() ?? '');
        $lastName = $this->normalizeForMatch($user->getLastName() ?? '');

        if ($firstName === '' || $lastName === '') {
            return false;
        }

        $normalizedLabel = $this->normalizeForMatch($label);

        return str_contains($normalizedLabel, $firstName) && str_contains($normalizedLabel, $lastName);
    }

    private function normalizeForMatch(string $value): string
    {
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT', $value);

        return strtoupper($transliterated !== false ? $transliterated : $value);
    }

    /**
     * woob doesn't expose a stock/fund distinction for synced holdings, but
     * fund/ETF names reliably contain a giveaway keyword ("UCITS ETF", "SICAV",
     * ...) while individual stocks don't — good enough to sort the common
     * cases without pretending to more precision than the data actually has.
     */
    private function guessInvestmentType(string $label): InvestmentType
    {
        $label = mb_strtoupper($label);
        $fundKeywords = ['ETF', 'UCITS', 'FCP', 'SICAV', 'OPCVM', 'TRACKER', 'FONDS', 'FUND'];

        foreach ($fundKeywords as $keyword) {
            if (str_contains($label, $keyword)) {
                return InvestmentType::ETF;
            }
        }

        return InvestmentType::STOCK;
    }
}
