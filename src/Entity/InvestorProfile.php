<?php

namespace App\Entity;

use App\Repository\InvestorProfileRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: InvestorProfileRepository::class)]
#[ORM\Table(name: 'investor_profiles')]
class InvestorProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['investor_profile:read'])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['investor_profile:read'])]
    private ?string $objective = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['investor_profile:read'])]
    private ?string $horizon = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['investor_profile:read'])]
    private ?string $reactionToLoss = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['investor_profile:read'])]
    private ?string $targetReturn = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['investor_profile:read'])]
    private ?string $maxLoss = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['investor_profile:read'])]
    private ?string $emergencyFund = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['investor_profile:read'])]
    private ?string $savingsRate = null;

    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['investor_profile:read'])]
    private ?array $knownProducts = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Groups(['investor_profile:read'])]
    private ?string $investingExperience = null;

    #[ORM\Column(nullable: true)]
    #[Groups(['investor_profile:read'])]
    private ?int $score = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getObjective(): ?string
    {
        return $this->objective;
    }

    public function setObjective(?string $objective): static
    {
        $this->objective = $objective;
        return $this;
    }

    public function getHorizon(): ?string
    {
        return $this->horizon;
    }

    public function setHorizon(?string $horizon): static
    {
        $this->horizon = $horizon;
        return $this;
    }

    public function getReactionToLoss(): ?string
    {
        return $this->reactionToLoss;
    }

    public function setReactionToLoss(?string $reactionToLoss): static
    {
        $this->reactionToLoss = $reactionToLoss;
        return $this;
    }

    public function getTargetReturn(): ?string
    {
        return $this->targetReturn;
    }

    public function setTargetReturn(?string $targetReturn): static
    {
        $this->targetReturn = $targetReturn;
        return $this;
    }

    public function getMaxLoss(): ?string
    {
        return $this->maxLoss;
    }

    public function setMaxLoss(?string $maxLoss): static
    {
        $this->maxLoss = $maxLoss;
        return $this;
    }

    public function getEmergencyFund(): ?string
    {
        return $this->emergencyFund;
    }

    public function setEmergencyFund(?string $emergencyFund): static
    {
        $this->emergencyFund = $emergencyFund;
        return $this;
    }

    public function getSavingsRate(): ?string
    {
        return $this->savingsRate;
    }

    public function setSavingsRate(?string $savingsRate): static
    {
        $this->savingsRate = $savingsRate;
        return $this;
    }

    public function getKnownProducts(): ?array
    {
        return $this->knownProducts;
    }

    public function setKnownProducts(?array $knownProducts): static
    {
        $this->knownProducts = $knownProducts;
        return $this;
    }

    public function getInvestingExperience(): ?string
    {
        return $this->investingExperience;
    }

    public function setInvestingExperience(?string $investingExperience): static
    {
        $this->investingExperience = $investingExperience;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(?int $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function computeScore(): int
    {
        $score = 0;

        $score += match ($this->objective) {
            'protection' => 0,
            'income' => 1,
            'growth' => 2,
            'speculation' => 3,
            default => 0
        };

        $score += match ($this->horizon) {
            'short' => 0,
            'medium' => 1,
            'long' => 2,
            'very_long' => 3,
            default => 0
        };

        $score += match ($this->reactionToLoss) {
            'sell' => 0,
            'wait' => 1,
            'buy' => 2,
            default => 0
        };

        $score += match ($this->targetReturn) {
            'safe' => 0,
            'balanced' => 1,
            'dynamic' => 2,
            'aggressive' => 3,
            default => 0
        };

        $score += match ($this->maxLoss) {
            'zero' => 0,
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            default => 0
        };

        $score += match ($this->emergencyFund) {
            'none' => 0,
            'low' => 1,
            'medium' => 2,
            'high' => 3,
            default => 0
        };

        $score += match ($this->savingsRate) {
            'low' => 0,
            'medium' => 1,
            'high' => 2,
            'very_high' => 3,
            default => 0
        };

        $score += match ($this->investingExperience) {
            'never' => 0,
            'beginner' => 1,
            'intermediate' => 2,
            'advanced' => 3,
            default => 0
        };

        return match(true) {
            $score <= 4  => 1,
            $score <= 9  => 2,
            $score <= 14 => 3,
            $score <= 19 => 4,
            default      => 5,
        };
    }
}
