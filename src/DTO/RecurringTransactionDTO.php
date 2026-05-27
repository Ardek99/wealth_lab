<?php

namespace App\DTO;

use App\Enum\TransactionType;
use Symfony\Component\Validator\Constraints as Assert;

class RecurringTransactionDTO
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
        #[Assert\Range(min: 1, max: 31)]
        private int $dayOfMonth,

        #[Assert\NotNull]
        private \DateTime $startDate,

        private ?\DateTime $endDate,

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

    public function getDayOfMonth(): int
    {
        return $this->dayOfMonth;
    }

    public function getStartDate(): \DateTime
    {
        return $this->startDate;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
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