<?php

namespace App\Entity;

use App\Repository\SauvegardePosteRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SauvegardePosteRepository::class)]
#[ORM\Table(name: 'sauvegarde_poste')]
class SauvegardePoste
{
    public const TYPE_POINT_RESTAURATION = 'POINT_RESTAURATION';
    public const TYPE_SAUVEGARDE_PROFIL = 'SAUVEGARDE_PROFIL';

    public const STATUT_SUCCES = 'SUCCES';
    public const STATUT_ECHEC = 'ECHEC';
    public const STATUT_EN_COURS = 'EN_COURS';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Equipement $equipement = null;

    #[ORM\Column(length: 100)]
    private ?string $nomMachine = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $adresseIp = null;

    #[ORM\Column(length: 50)]
    private ?string $typeOperation = null; // POINT_RESTAURATION ou SAUVEGARDE_PROFIL

    #[ORM\Column(length: 30)]
    private ?string $statut = self::STATUT_SUCCES;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lancePar = null; // Nom d'utilisateur admin

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $details = null; // Logs, résumé des fichiers modifiés/copiés

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $tailleEstimee = null; // Ex: "1.4 Go", "14 maquettes modifiées"

    #[ORM\Column]
    private ?\DateTimeImmutable $dateExecution = null;

    public function __construct()
    {
        $this->dateExecution = new \DateTimeImmutable();
        $this->statut = self::STATUT_SUCCES;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function setEquipement(?Equipement $equipement): static
    {
        $this->equipement = $equipement;
        return $this;
    }

    public function getNomMachine(): ?string
    {
        return $this->nomMachine;
    }

    public function setNomMachine(string $nomMachine): static
    {
        $this->nomMachine = $nomMachine;
        return $this;
    }

    public function getAdresseIp(): ?string
    {
        return $this->adresseIp;
    }

    public function setAdresseIp(?string $adresseIp): static
    {
        $this->adresseIp = $adresseIp;
        return $this;
    }

    public function getTypeOperation(): ?string
    {
        return $this->typeOperation;
    }

    public function setTypeOperation(string $typeOperation): static
    {
        $this->typeOperation = $typeOperation;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getLancePar(): ?string
    {
        return $this->lancePar;
    }

    public function setLancePar(?string $lancePar): static
    {
        $this->lancePar = $lancePar;
        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;
        return $this;
    }

    public function getTailleEstimee(): ?string
    {
        return $this->tailleEstimee;
    }

    public function setTailleEstimee(?string $tailleEstimee): static
    {
        $this->tailleEstimee = $tailleEstimee;
        return $this;
    }

    public function getDateExecution(): ?\DateTimeImmutable
    {
        return $this->dateExecution;
    }

    public function setDateExecution(\DateTimeImmutable $dateExecution): static
    {
        $this->dateExecution = $dateExecution;
        return $this;
    }

    public function isSucces(): bool
    {
        return $this->statut === self::STATUT_SUCCES;
    }

    public function isPointRestauration(): bool
    {
        return $this->typeOperation === self::TYPE_POINT_RESTAURATION;
    }

    public function isSauvegardeProfil(): bool
    {
        return $this->typeOperation === self::TYPE_SAUVEGARDE_PROFIL;
    }
}

