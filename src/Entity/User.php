<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_PHONE_NUMBER', fields: ['phoneNumber'])]
#[ORM\Table(name: '`users`')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $firstName = null;

    #[ORM\Column(length: 180)]
    private ?string $lastName = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $phoneNumber = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $periodStartDay = 1;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    /**
     * @var Collection<int, Account>
     */
    #[ORM\OneToMany(targetEntity: Account::class, mappedBy: 'user')]
    private Collection $accounts;

    /**
     * @var Collection<int, Goal>
     */
    #[ORM\OneToMany(targetEntity: Goal::class, mappedBy: 'user')]
    private Collection $goals;

    /**
     * @var Collection<int, Investment>
     */
    #[ORM\OneToMany(targetEntity: Investment::class, mappedBy: 'user')]
    private Collection $investments;

    /**
     * @var Collection<int, RecurringTransaction>
     */
    #[ORM\OneToMany(targetEntity: RecurringTransaction::class, mappedBy: 'user')]
    private Collection $recurringTransactions;

    /**
     * @var Collection<int, BankConnection>
     */
    #[ORM\OneToMany(targetEntity: BankConnection::class, mappedBy: 'user')]
    private Collection $bankConnections;

    public function __construct()
    {
        $this->accounts = new ArrayCollection();
        $this->goals = new ArrayCollection();
        $this->investments = new ArrayCollection();
        $this->recurringTransactions = new ArrayCollection();
        $this->bankConnections = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): static
    {
        $this->firstName = $firstName;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): static
    {
        $this->lastName = $lastName;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhoneNumber(): ?string
    {
        return $this->phoneNumber;
    }

    public function setPhoneNumber(string $phoneNumber): static
    {
        $this->phoneNumber = $phoneNumber;

        return $this;
    }

    public function getPeriodStartDay(): int
    {
        return $this->periodStartDay;
    }

    public function setPeriodStartDay(int $periodStartDay): static
    {
        $this->periodStartDay = $periodStartDay;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    /**
     * @return Collection<int, Account>
     */
    public function getAccounts(): Collection
    {
        return $this->accounts;
    }

    public function addAccount(Account $account): static
    {
        if (!$this->accounts->contains($account)) {
            $this->accounts->add($account);
            $account->setUser($this);
        }

        return $this;
    }

    public function removeAccount(Account $account): static
    {
        if ($this->accounts->removeElement($account)) {
            // set the owning side to null (unless already changed)
            if ($account->getUser() === $this) {
                $account->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Goal>
     */
    public function getGoals(): Collection
    {
        return $this->goals;
    }

    public function addGoal(Goal $goal): static
    {
        if (!$this->goals->contains($goal)) {
            $this->goals->add($goal);
            $goal->setUser($this);
        }

        return $this;
    }

    public function removeGoal(Goal $goal): static
    {
        if ($this->goals->removeElement($goal)) {
            // set the owning side to null (unless already changed)
            if ($goal->getUser() === $this) {
                $goal->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Investment>
     */
    public function getInvestments(): Collection
    {
        return $this->investments;
    }

    public function addInvestment(Investment $investment): static
    {
        if (!$this->investments->contains($investment)) {
            $this->investments->add($investment);
            $investment->setUser($this);
        }

        return $this;
    }

    public function removeInvestment(Investment $investment): static
    {
        if ($this->investments->removeElement($investment)) {
            // set the owning side to null (unless already changed)
            if ($investment->getUser() === $this) {
                $investment->setUser(null);
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
            $recurringTransaction->setUser($this);
        }

        return $this;
    }

    public function removeRecurringTransaction(RecurringTransaction $recurringTransaction): static
    {
        if ($this->recurringTransactions->removeElement($recurringTransaction)) {
            // set the owning side to null (unless already changed)
            if ($recurringTransaction->getUser() === $this) {
                $recurringTransaction->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BankConnection>
     */
    public function getBankConnections(): Collection
    {
        return $this->bankConnections;
    }

    public function addBankConnection(BankConnection $bankConnection): static
    {
        if (!$this->bankConnections->contains($bankConnection)) {
            $this->bankConnections->add($bankConnection);
            $bankConnection->setUser($this);
        }

        return $this;
    }

    public function removeBankConnection(BankConnection $bankConnection): static
    {
        if ($this->bankConnections->removeElement($bankConnection)) {
            // set the owning side to null (unless already changed)
            if ($bankConnection->getUser() === $this) {
                $bankConnection->setUser(null);
            }
        }

        return $this;
    }
}
