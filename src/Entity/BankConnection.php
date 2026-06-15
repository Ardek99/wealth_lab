<?php 

namespace App\Entity;

use App\Enum\BankConnectionStatus;
use App\Repository\BankConnectionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: BankConnectionRepository::class)]
#[ORM\Table(name: 'bank_connections')]
class BankConnection 
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['bank_connection:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Groups(['bank_connection:read'])]
    private ?string $module = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $credentials = null;

    #[ORM\Column(length: 255)]
    #[Groups(['bank_connection:read'])]
    private ?string $label = null;

    #[ORM\Column(enumType: BankConnectionStatus::class)]
    #[Groups(['bank_connection:read'])]
    private ?BankConnectionStatus $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['bank_connection:read'])]
    private ?string $lastErrorMessage = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['bank_connection:read'])]
    private ?\DateTimeInterface $lastSyncDate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['bank_connection:read'])]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'bankConnections')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    /**
     * @var Collection<int, Account>
     */
    #[ORM\OneToMany(targetEntity: Account::class, mappedBy: 'bankConnection')]
    private Collection $accounts;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->status = BankConnectionStatus::ACTIVE;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModule(): ?string
    {
        return $this->module;
    }

    public function setModule(string $module): static
    {
        $this->module = $module;

        return $this;
    }

    public function getCredentials(): ?string
    {
        return $this->credentials;
    }

    public function setCredentials(string $credentials): static
    {
        $this->credentials = $credentials;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getStatus(): ?BankConnectionStatus
    {
        return $this->status;
    }

    public function setStatus(BankConnectionStatus $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
    }

    public function setLastErrorMessage(?string $lastErrorMessage): static
    {
        $this->lastErrorMessage = $lastErrorMessage;

        return $this;
    }

    public function getLastSyncDate(): ?\DateTimeInterface
    {
        return $this->lastSyncDate;
    }

    public function setLastSyncDate(?\DateTimeInterface $lastSyncDate): static
    {
        $this->lastSyncDate = $lastSyncDate;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

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