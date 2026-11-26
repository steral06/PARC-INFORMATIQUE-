<?php

namespace App\Entity;

use App\Repository\WebAuthnCredentialRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: WebAuthnCredentialRepository::class)]
#[ORM\Table(name: 'webauthn_credential')]
class WebAuthnCredential
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** Base64url-encoded credential ID (unique per credential) */
    #[ORM\Column(length: 512, unique: true)]
    private string $credentialId = '';

    /** COSE public key, stored as PEM */
    #[ORM\Column(type: 'text')]
    private string $publicKey = '';

    /** Algorithm: -7 (ES256) or -257 (RS256) */
    #[ORM\Column]
    private int $algorithm = -7;

    /** Sign counter (anti-cloning protection) */
    #[ORM\Column]
    private int $signCount = 0;

    /** Human-readable device name */
    #[ORM\Column(length: 128)]
    private string $deviceName = 'Appareil inconnu';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $lastUsedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getCredentialId(): string { return $this->credentialId; }
    public function setCredentialId(string $credentialId): static { $this->credentialId = $credentialId; return $this; }

    public function getPublicKey(): string { return $this->publicKey; }
    public function setPublicKey(string $publicKey): static { $this->publicKey = $publicKey; return $this; }

    public function getAlgorithm(): int { return $this->algorithm; }
    public function setAlgorithm(int $algorithm): static { $this->algorithm = $algorithm; return $this; }

    public function getSignCount(): int { return $this->signCount; }
    public function setSignCount(int $signCount): static { $this->signCount = $signCount; return $this; }

    public function getDeviceName(): string { return $this->deviceName; }
    public function setDeviceName(string $deviceName): static { $this->deviceName = $deviceName; return $this; }

    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }

    public function getLastUsedAt(): ?\DateTimeImmutable { return $this->lastUsedAt; }
    public function setLastUsedAt(?\DateTimeImmutable $lastUsedAt): static { $this->lastUsedAt = $lastUsedAt; return $this; }
}

