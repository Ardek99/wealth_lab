<?php

namespace App\MessageHandler;

use App\Message\SyncBankConnectionMessage;
use App\Repository\BankConnectionRepository;
use App\Service\BankSyncService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SyncBankConnectionMessageHandler
{
    public function __construct(
        private BankConnectionRepository $bankConnectionRepository,
        private BankSyncService $bankSyncService,
    ) {}

    public function __invoke(SyncBankConnectionMessage $message): void
    {
        $connection = $this->bankConnectionRepository->find($message->bankConnectionId);

        if (!$connection) {
            return;
        }

        $this->bankSyncService->sync($connection);
    }
}
