<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class BankConnectionDTO
{
    public function __construct(
        #[Assert\NotBlank]
        private string $module,

        #[Assert\NotBlank]
        #[Assert\Length(min: 2, max: 255)]
        private string $label,

        #[Assert\NotNull]
        private array $config,
    ) {}

    public function getModule(): string { return $this->module; }
    public function getLabel(): string { return $this->label; }
    public function getConfig(): array { return $this->config; }
}
