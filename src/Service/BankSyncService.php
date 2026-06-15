<?php

namespace App\Service;

use App\Entity\Account;
use App\Entity\BankConnection;
use App\Entity\Transaction;
use App\Enum\AccountType;
use App\Enum\BankConnectionStatus;
use App\Enum\TransactionType;
use App\Repository\CategoryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class BankSyncService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private CredentialEncryptionService $encryptionService,
        private EntityManagerInterface $em,
        private CategoryRepository $categoryRepository,
        private string $syncUrl,
    ) {}

    public function sync(BankConnection $connection): void
    {
        set_time_limit(120);

        $config = $this->encryptionService->decrypt($connection->getCredentials());

        $response = $this->httpClient->request('POST', $this->syncUrl . '/sync', [
            'json' => [
                'module' => $connection->getModule(),
                'config' => $config,
                'since' => (new \DateTime('-3 months'))->format('Y-m-d'),
            ],
            'timeout' => 120,
        ]);
        $data = $response->toArray(false);

        // Cas StatusResponse : pas de sync possible
        if (isset($data['status'])) {
            $connection->setStatus(match ($data['status']) {
                'invalid_credentials' => BankConnectionStatus::INVALID_CREDENTIALS,
                '2fa_required', 'captcha_required' => BankConnectionStatus::ACTION_NEEDED,
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

            foreach ($accountData['transactions'] as $trData) {
                $exists = $transactionRepo->findOneBy([
                    'account' => $account,
                    'externalId' => $trData['externalId'],
                ]);
                if ($exists) {
                    continue; // déjà importée
                }

                $amount = (float) $trData['amount'];
                $category = $this->categoryRepository->findOneBy([
                    'name' => $amount >= 0 ? 'Autres revenus' : 'Autres dépenses',
                ]);

                $transaction = (new Transaction())
                    ->setAccount($account)
                    ->setExternalId($trData['externalId'])
                    ->setName($trData['label'])
                    ->setAmount((string) $amount)
                    ->setDate(new \DateTime($trData['date']))
                    ->setType($amount >= 0 ? TransactionType::INCOME : TransactionType::EXPENSE)
                    ->setCategory($category);

                $this->em->persist($transaction);
            }
        }

        $connection->setStatus(BankConnectionStatus::ACTIVE);
        $connection->setLastErrorMessage(null);
        $connection->setLastSyncDate(new \DateTime());
        $this->em->flush();
    }
}
