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
        #[Assert\Length(max: 50)]
        private ?string $ticker = null,
        #[Assert\NotBlank]
        #[Assert\Positive]
        private float $quantity,
        #[Assert\NotBlank]
        #[Assert\Positive]
        private float $purchasePrice,
        #[Assert\NotBlank]
        #[Assert\Type("\DateTimeInterface")]
        private \DateTimeInterface $purchaseDate,
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

    public function getTicker(): ?string
    {
        return $this->ticker;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getPurchasePrice(): float
    {
        return $this->purchasePrice;
    }

    public function getPurchaseDate(): \DateTimeInterface
    {
        return $this->purchaseDate;
    }
}