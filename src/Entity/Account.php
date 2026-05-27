<?php

namespace App\Entity;

use App\Enum\AccountType;
use App\Repository\AccountRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: AccountRepository::class)]
#[ORM\Table(name: 'accounts')]
class Account
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['account:read', 'transaction:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['account:read', 'transaction:read'])]
    private ?string $name = null;

    #[ORM\Column(enumType: AccountType::class)]
    #[Groups(['account:read'])]
    private ?AccountType $type = null;

    #[ORM\ManyToOne(inversedBy: 'accounts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, Transaction>
     */
    #[ORM\OneToMany(targetEntity: Transaction::class, mappedBy: 'account', cascade: ['remove'])]
    private Collection $transactions;

    /**
     * @var Collection<int, RecurringTransaction>
     */
    #[ORM\OneToMany(targetEntity: RecurringTransaction::class, mappedBy: 'account')]
    private Collection $recurringTransactions;

    public function __construct()
    {
        $this->transactions = new ArrayCollection();
        $this->recurringTransactions = new ArrayCollection();
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

    public function getType(): ?AccountType
    {
        return $this->type;
    }

    public function setType(AccountType $type): static
    {
        $this->type = $type;

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

    /**
     * @return Collection<int, Transaction>
     */
    public function getTransactions(): Collection
    {
        return $this->transactions;
    }

    public function addTransaction(Transaction $transaction): static
    {
        if (!$this->transactions->contains($transaction)) {
            $this->transactions->add($transaction);
            $transaction->setAccount($this);
        }

        return $this;
    }

    public function removeTransaction(Transaction $transaction): static
    {
        if ($this->transactions->removeElement($transaction)) {
            // set the owning side to null (unless already changed)
            if ($transaction->getAccount() === $this) {
                $transaction->setAccount(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, RecurringTransaction>
     */
    public function getRecurringTransactions(): Collection
    {
        return $this->recurringTransactions;
    }

    public function addRecurringTransaction(RecurringTransaction $recurringTransaction): static
    {
        if (!$this->recurringTransactions->contains($recurringTransaction)) {
            $this->recurringTransactions->add($recurringTransaction);
            $recurringTransaction->setAccount($this);
        }

        return $this;
    }

    public function removeRecurringTransaction(RecurringTransaction $recurringTransaction): static
    {
        if ($this->recurringTransactions->removeElement($recurringTransaction)) {
            // set the owning side to null (unless already changed)
            if ($recurringTransaction->getAccount() === $this) {
                $recurringTransaction->setAccount(null);
            }
        }

        return $this;
    }
}
