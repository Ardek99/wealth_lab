<?php

namespace App\DTO;

use App\Enum\AccountType;
use Symfony\Component\Validator\Constraints as Assert;

class AccountDTO
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        private string $name,

        #[Assert\NotNull]
        private AccountType $type,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getType(): AccountType
    {
        return $this->type;
    }
}