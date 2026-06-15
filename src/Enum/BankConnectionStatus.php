<?php 

namespace App\Enum;

enum BankConnectionStatus: string
{
    case ACTIVE = 'active';
    case INVALID_CREDENTIALS = 'invalid_credentials';
    case ACTION_NEEDED = 'action_needed';
    case ERROR = 'error';
    case PENDING = 'pending';
}