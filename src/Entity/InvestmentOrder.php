<?php

namespace App\Entity;

use App\Enum\InvestmentOrderDirection;
use App\Repository\InvestmentOrderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: InvestmentOrderRepository::class)]
#[ORM\Table(name: 'investment_orders')]
#[ORM\UniqueConstraint(name: 'uniq_investment_order_external', columns: ['investment_id', 'external_id'])]
class InvestmentOrder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['investment_order:read'])]
    private ?int $id = null;

    #[ORM\Column(enumType: InvestmentOrderDirection::class)]
    #[Groups(['investment_order:read'])]
    private ?InvestmentOrderDirection $direction = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Groups(['investment_order:read'])]
    private ?string $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Groups(['investment_order:read'])]
    private ?string $unitPrice = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['investment_order:read'])]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;

    #[ORM\ManyToOne(inversedBy: 'orders')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Investment $investment = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDirection(): ?InvestmentOrderDirection
    {
        return $this->direction;
    }

    public function setDirection(InvestmentOrderDirection $direction): static
    {
        $this->direction = $direction;

        return $this;
    }

    public function getQuantity(): ?string
    {
        return $this->quantity;
    }

    public function setQuantity(string $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): static
    {
        $this->unitPrice = $unitPrice;

        return $this;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = \DateTime::createFromInterface($date);

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): static
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getInvestment(): ?Investment
    {
        return $this->investment;
    }

    public function setInvestment(?Investment $investment): static
    {
        $this->investment = $investment;

        return $this;
    }

    #[Groups(['investment_order:read'])]
    public function getValue(): float
    {
        return round((float) $this->quantity * (float) $this->unitPrice, 2);
    }
}
