<?php

namespace App\Enum;

enum InvestmentType: string
{
    case STOCK = 'stock';
    case ETF = 'etf';
    case CRYPTO = 'crypto';
    case OTHER = 'other';
}