<?php

namespace App\Entity;

use App\Enum\InvestmentType;
use App\Repository\InvestmentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: InvestmentRepository::class)]
class Investment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['investment:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['investment:read'])]
    private ?string $name = null;

    #[ORM\Column(enumType: InvestmentType::class)]
    #[Groups(['investment:read'])]
    private ?InvestmentType $type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['investment:read'])]
    private ?string $amount = null;

    #[ORM\ManyToOne(inversedBy: 'investments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): ?InvestmentType
    {
        return $this->type;
    }

    public function setType(InvestmentType $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }
}
