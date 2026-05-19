<?php

namespace App\DTO;

use App\Enum\InvestmentType;
use Symfony\Component\Validator\Constraints as Assert;

class InvestmentDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        private string $name,
        #[Assert\NotNull]
        private InvestmentType $type,
        #[Assert\NotNull]
        #[Assert\Positive]
        private float $amount,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): InvestmentType
    {
        return $this->type;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }
}