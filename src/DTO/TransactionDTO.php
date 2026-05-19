<?php

namespace App\DTO;

use App\Enum\TransactionType;
use Symfony\Component\Validator\Constraints as Assert;

class TransactionDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        private string $name,

        #[Assert\NotNull]
        private TransactionType $type,

        #[Assert\NotNull]
        #[Assert\Positive]
        private float $amount,

        #[Assert\NotNull]
        private \DateTime $date,

        private ?string $description,

        #[Assert\NotNull]
        #[Assert\Positive]
        private int $accountId,

        #[Assert\NotNull]
        #[Assert\Positive]
        private int $categoryId,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): TransactionType
    {
        return $this->type;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getDate(): \DateTime
    {
        return $this->date;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getAccountId(): int
    {
        return $this->accountId;
    }

    public function getCategoryId(): int
    {
        return $this->categoryId;
    }
}