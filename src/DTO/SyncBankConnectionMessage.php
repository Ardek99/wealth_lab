<?php

namespace App\Message;

class SyncBankConnectionMessage
{
    public function __construct(
        public readonly int $bankConnectionId,
    ) {
    }
}
