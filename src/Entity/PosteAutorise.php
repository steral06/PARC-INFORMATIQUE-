<?php

namespace App\Entity;

use App\Repository\PosteAutoriseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PosteAutoriseRepository::class)]
class PosteAutorise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 45, unique: true)]
    private ?string $adresseIp = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isAutorise = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $datePremiereConnexion = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDerniereConnexion = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateDerniereActivite = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getAdresseIp(): ?string
    {
        return $this->adresseIp;
    }

    public function setAdresseIp(string $adresseIp): static
    {
        $this->adresseIp = $adresseIp;

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

    public function isAutorise(): bool
    {
        return $this->isAutorise;
    }

    public function setIsAutorise(bool $isAutorise): static
    {
        $this->isAutorise = $isAutorise;

        return $this;
    }

    public function getDatePremiereConnexion(): ?\DateTimeImmutable
    {
        return $this->datePremiereConnexion;
    }

    public function setDatePremiereConnexion(?\DateTimeImmutable $datePremiereConnexion): static
    {
        $this->datePremiereConnexion = $datePremiereConnexion;

        return $this;
    }

    public function getDateDerniereConnexion(): ?\DateTimeImmutable
    {
        return $this->dateDerniereConnexion;
    }

    public function setDateDerniereConnexion(?\DateTimeImmutable $dateDerniereConnexion): static
    {
        $this->dateDerniereConnexion = $dateDerniereConnexion;

        return $this;
    }

    public function getDateDerniereActivite(): ?\DateTimeImmutable
    {
        return $this->dateDerniereActivite;
    }

    public function setDateDerniereActivite(?\DateTimeImmutable $dateDerniereActivite): static
    {
        $this->dateDerniereActivite = $dateDerniereActivite;

        return $this;
    }

    public function isEnLigne(int $thresholdSeconds = 15): bool
    {
        if (!$this->dateDerniereActivite) {
            return false;
        }

        return (time() - $this->dateDerniereActivite->getTimestamp()) <= $thresholdSeconds;
    }
}
