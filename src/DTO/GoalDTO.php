<?php

namespace App\DTO;
use Symfony\Component\Validator\Constraints as Assert;

class GoalDTO
{
    public function __construct(

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        public string $name,
        #[Assert\NotNull]
        #[Assert\Positive]
        public float $targetAmount,
        #[Assert\NotNull]
        #[Assert\Positive]
        public float $currentAmount,
        #[Assert\NotNull]
        public \DateTime $targetDate
    )
    {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTargetAmount(): float
    {
        return $this->targetAmount;
    }

    public function getCurrentAmount(): float
    {
        return $this->currentAmount;
    }

    public function getTargetDate(): \DateTime
    {
        return $this->targetDate;
    }
}