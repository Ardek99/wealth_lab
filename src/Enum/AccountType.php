<?php

namespace App\Enum;

enum AccountType: string
{
    case CHECKING = 'checking';
    case SAVINGS = 'savings';
    case CREDIT = 'credit';
    case INVESTMENT = 'investment';
}