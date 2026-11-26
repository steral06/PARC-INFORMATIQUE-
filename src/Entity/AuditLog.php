<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_log')]
#[ORM\Index(name: 'idx_audit_action', columns: ['action'])]
#[ORM\Index(name: 'idx_audit_level', columns: ['level'])]
#[ORM\Index(name: 'idx_audit_status', columns: ['status'])]
#[ORM\Index(name: 'idx_audit_entity', columns: ['entity_type'])]
#[ORM\Index(name: 'idx_audit_user', columns: ['username'])]
#[ORM\Index(name: 'idx_audit_created', columns: ['created_at'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(length: 20)]
    private ?string $level = 'INFO';

    #[ORM\Column(length: 20)]
    private ?string $status = 'SUCCES';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $entityType = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $entityId = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $details = null;

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $previousHash = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $hash = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(?string $entityType): static
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): ?string
    {
        return $this->entityId;
    }

    public function setEntityId(?string $entityId): static
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): static
    {
        $this->details = $details;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getPreviousHash(): ?string
    {
        return $this->previousHash;
    }

    public function setPreviousHash(?string $previousHash): static
    {
        $this->previousHash = $previousHash;
        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): static
    {
        $this->hash = $hash;
        return $this;
    }

    public function getActionLabel(): string
    {
        $labels = [
            'CONNEXION' => 'Connexion',
            'DECONNEXION' => 'Déconnexion',
            'ECHEC_CONNEXION' => 'Échec connexion',
            'ACCES_REFUSE' => 'Accès refusé',
            'CREATION' => 'Création',
            'MODIFICATION' => 'Modification',
            'SUPPRESSION' => 'Suppression',
            'CONFIGURATION' => 'Configuration',
        ];
        return $labels[$this->action] ?? $this->action;
    }

    public function getLevelLabel(): string
    {
        return match ($this->level) {
            'INFO' => 'Info',
            'WARNING' => 'Avertissement',
            'ERROR' => 'Erreur',
            default => $this->level,
        };
    }

    public function getStatusLabel(): string
    {
        return $this->status === 'SUCCES' ? 'Succès' : 'Échec';
    }

    public function getActionBadgeClass(): string
    {
        $classes = [
            'CONNEXION' => 'bg-primary-subtle text-primary border-primary-subtle',
            'DECONNEXION' => 'bg-info-subtle text-info border-info-subtle',
            'ECHEC_CONNEXION' => 'bg-warning-subtle text-dark border-warning-subtle',
            'ACCES_REFUSE' => 'bg-danger-subtle text-danger border-danger-subtle',
            'CREATION' => 'bg-success-subtle text-success border-success-subtle',
            'MODIFICATION' => 'bg-warning-subtle text-dark border-warning-subtle',
            'SUPPRESSION' => 'bg-danger-subtle text-danger border-danger-subtle',
            'CONFIGURATION' => 'bg-secondary-subtle text-dark border-secondary-subtle',
        ];
        return $classes[$this->action] ?? 'bg-light text-dark border';
    }

    public function getLevelBadgeClass(): string
    {
        return match ($this->level) {
            'INFO' => 'bg-primary-subtle text-primary border-primary-subtle',
            'WARNING' => 'bg-warning-subtle text-dark border-warning-subtle',
            'ERROR' => 'bg-danger-subtle text-danger border-danger-subtle',
            default => 'bg-light text-dark border',
        };
    }

    public function getStatusBadgeClass(): string
    {
        return $this->status === 'SUCCES'
            ? 'bg-success-subtle text-success border-success-subtle'
            : 'bg-danger-subtle text-danger border-danger-subtle';
    }
}