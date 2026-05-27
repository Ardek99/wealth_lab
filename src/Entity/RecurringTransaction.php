<?php

namespace App\Entity;

use App\Enum\TransactionType;
use App\Repository\RecurringTransactionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: RecurringTransactionRepository::class)]
class RecurringTransaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['recurring_transaction:read', 'transaction:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['recurring_transaction:read'])]
    private ?string $name = null;

    #[ORM\Column(enumType: TransactionType::class)]
    #[Groups(['recurring_transaction:read'])]
    private ?TransactionType $type = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Groups(['recurring_transaction:read'])]
    private ?string $amount = null;

    #[ORM\Column]
    #[Groups(['recurring_transaction:read'])]
    private ?int $dayOfMonth = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Groups(['recurring_transaction:read'])]
    private ?\DateTime $startDate = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['recurring_transaction:read'])]
    private ?\DateTime $endDate = null;

    #[ORM\ManyToOne(inversedBy: 'recurringTransactions')]
    #[Groups(['recurring_transaction:read'])]
    private ?Account $account = null;

    #[ORM\ManyToOne(inversedBy: 'recurringTransactions')]
    #[Groups(['recurring_transaction:read'])]
    private ?Category $category = null;

    #[ORM\ManyToOne(inversedBy: 'recurringTransactions')]
    #[Groups(['recurring_transaction:read'])]
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

    public function getType(): ?TransactionType
    {
        return $this->type;
    }

    public function setType(TransactionType $type): static
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

    public function getDayOfMonth(): ?int
    {
        return $this->dayOfMonth;
    }

    public function setDayOfMonth(int $dayOfMonth): static
    {
        $this->dayOfMonth = $dayOfMonth;

        return $this;
    }

    public function getStartDate(): ?\DateTime
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTime $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTime
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTime $endDate): static
    {
        $this->endDate = $endDate;

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

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): static
    {
        $this->category = $category;

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
