<?php

namespace App\Entity;

use App\Enum\InvestmentType;
use App\Repository\InvestmentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: InvestmentRepository::class)]
#[ORM\Table(name: 'investments')]
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

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['investment:read'])]
    private ?string $ticker = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Groups(['investment:read'])]
    private ?string $quantity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6)]
    #[Groups(['investment:read'])]
    private ?string $purchasePrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 15, scale: 6, nullable: true)]
    #[Groups(['investment:read'])]
    private ?string $currentPrice = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['investment:read'])]
    private ?\DateTimeInterface $purchaseDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['investment:read'])]
    private ?\DateTimeInterface $currentPriceUpdatedAt = null;

    #[ORM\ManyToOne(inversedBy: 'investments')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'investments')]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['investment:read'])]
    private ?Account $account = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $externalId = null;

    /**
     * @var Collection<int, InvestmentOrder>
     */
    #[ORM\OneToMany(targetEntity: InvestmentOrder::class, mappedBy: 'investment', cascade: ['remove'])]
    private Collection $orders;

    public function __construct()
    {
        $this->orders = new ArrayCollection();
    }

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

    public function getTicker(): ?string
    {
        return $this->ticker;
    }

    public function setTicker(?string $ticker): static
    {
        $this->ticker = $ticker;

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

    public function getPurchasePrice(): ?string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(string $purchasePrice): static
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getCurrentPrice(): ?string
    {
        return $this->currentPrice;
    }

    public function setCurrentPrice(?string $currentPrice): static
    {
        $this->currentPrice = $currentPrice;

        return $this;
    }

    public function getPurchaseDate(): ?\DateTimeInterface
    {
        return $this->purchaseDate;
    }

    public function setPurchaseDate(\DateTimeInterface $purchaseDate): static
    {
        $this->purchaseDate = \DateTime::createFromInterface($purchaseDate);
        return $this;
    }

    public function getCurrentPriceUpdatedAt(): ?\DateTimeInterface
    {
        return $this->currentPriceUpdatedAt;
    }

    public function setCurrentPriceUpdatedAt(?\DateTimeInterface $currentPriceUpdatedAt): static
    {
        $this->currentPriceUpdatedAt = $currentPriceUpdatedAt;

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

    public function getAccount(): ?Account
    {
        return $this->account;
    }

    public function setAccount(?Account $account): static
    {
        $this->account = $account;

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

    /**
     * @return Collection<int, InvestmentOrder>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    #[Groups(['investment:read'])]
    public function getPurchaseValue(): float
    {
        return round((float)$this->quantity * (float)$this->purchasePrice, 2);
    }

    #[Groups(['investment:read'])]
    public function getCurrentValue(): ?float
    {
        if ($this->currentPrice === null) return null;
        return round((float)$this->quantity * (float)$this->currentPrice, 2);
    }

    #[Groups(['investment:read'])]
    public function getGainLoss(): ?float
    {
        $current = $this->getCurrentValue();
        if ($current === null) return null;
        return round($current - $this->getPurchaseValue(), 2);
    }

    #[Groups(['investment:read'])]
    public function getGainLossPercent(): ?float
    {
        $purchase = $this->getPurchaseValue();
        if ($purchase == 0 || $this->currentPrice === null) return null;
        return round((($this->getCurrentValue() - $purchase) / $purchase) * 100, 2);
    }
}
