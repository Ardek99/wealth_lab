<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class InvestorProfileDTO
{
    #[Assert\Choice(choices: ['protection', 'income', 'growth', 'speculation'])]
    public ?string $objective = null;

    #[Assert\Choice(choices: ['short', 'medium', 'long', 'very_long'])]
    public ?string $horizon = null;

    #[Assert\Choice(choices: ['sell', 'wait', 'buy'])]
    public ?string $reactionToLoss = null;

    #[Assert\Choice(choices: ['safe', 'balanced', 'dynamic', 'aggressive'])]
    public ?string $targetReturn = null;

    #[Assert\Choice(choices: ['zero', 'low', 'medium', 'high'])]
    public ?string $maxLoss = null;

    #[Assert\Choice(choices: ['none', 'low', 'medium', 'high'])]
    public ?string $emergencyFund = null;

    #[Assert\Choice(choices: ['low', 'medium', 'high', 'very_high'])]
    public ?string $savingsRate = null;

    #[Assert\All([
        new Assert\Choice(choices: ['livret', 'assurance_vie', 'etf', 'stocks', 'crypto', 'derivatives'])
    ])]
    public ?array $knownProducts = null;

    #[Assert\Choice(choices: ['never', 'beginner', 'intermediate', 'advanced'])]
    public ?string $investingExperience = null;

    #[Assert\Choice(choices: ['cdi', 'cdd', 'alternance', 'freelance', 'fonctionnaire', 'retraite', 'etudiant', 'sans_emploi'])]
    public ?string $professionalStatus = null;

    #[Assert\Choice(choices: ['single', 'couple', 'married', 'with_children'])]
    public ?string $familyStatus = null;

    #[Assert\Choice(choices: ['18_25', '26_35', '36_45', '46_55', '55_plus'])]
    public ?string $ageRange = null;

    public function getObjective(): ?string { return $this->objective; }
    public function getHorizon(): ?string { return $this->horizon; }
    public function getReactionToLoss(): ?string { return $this->reactionToLoss; }
    public function getTargetReturn(): ?string { return $this->targetReturn; }
    public function getMaxLoss(): ?string { return $this->maxLoss; }
    public function getEmergencyFund(): ?string { return $this->emergencyFund; }
    public function getSavingsRate(): ?string { return $this->savingsRate; }
    public function getKnownProducts(): ?array { return $this->knownProducts; }
    public function getInvestingExperience(): ?string { return $this->investingExperience; }
    public function getProfessionalStatus(): ?string { return $this->professionalStatus; }
    public function getFamilyStatus(): ?string { return $this->familyStatus; }
    public function getAgeRange(): ?string { return $this->ageRange; }
}
