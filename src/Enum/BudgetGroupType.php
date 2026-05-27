<?php

namespace App\Enum;

enum BudgetGroupType: string
{
    case NEEDS = 'needs';
    case WANTS = 'wants';
}