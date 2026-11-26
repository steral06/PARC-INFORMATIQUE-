<?php

namespace App\Entity;

use App\Repository\TicketMaintenanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TicketMaintenanceRepository::class)]
#[ORM\Table(name: 'ticket_maintenance')]
class TicketMaintenance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Equipement $equipement = null;

    #[ORM\Column(length: 150)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 50)]
    private ?string $priorite = 'Moyenne'; // Basse, Moyenne, Haute, Critique

    #[ORM\Column(length: 50)]
    private ?string $statut = 'Nouveau'; // Nouveau, En cours, Résolu, Annulé

    #[ORM\Column(length: 30)]
    private ?string $typeMaintenance = 'DSI'; // 'DSI' (interne rapide) ou 'Prestataire' (externe)

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prestataireNom = null; // Nom entreprise prestataire (ex: Dell SAV, HP, ReparTout)

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $numeroBon = null; // N° de devis ou bon de prise en charge

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateRetourPrevue = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $technicien = null;

    #[ORM\Column(nullable: true)]
    private ?float $cout = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateCreation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateResolution = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $solution = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTimeImmutable();
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

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPriorite(): ?string
    {
        return $this->priorite;
    }

    public function setPriorite(string $priorite): static
    {
        $this->priorite = $priorite;
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

    public function getTechnicien(): ?string
    {
        return $this->technicien;
    }

    public function setTechnicien(?string $technicien): static
    {
        $this->technicien = $technicien;
        return $this;
    }

    public function getCout(): ?float
    {
        return $this->cout;
    }

    public function setCout(?float $cout): static
    {
        $this->cout = $cout;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeImmutable
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeImmutable $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getDateResolution(): ?\DateTimeInterface
    {
        return $this->dateResolution;
    }

    public function setDateResolution(?\DateTimeInterface $dateResolution): static
    {
        $this->dateResolution = $dateResolution;
        return $this;
    }

    public function getSolution(): ?string
    {
        return $this->solution;
    }

    public function setSolution(?string $solution): static
    {
        $this->solution = $solution;
        return $this;
    }

    public function getTypeMaintenance(): ?string
    {
        return $this->typeMaintenance;
    }

    public function setTypeMaintenance(string $typeMaintenance): static
    {
        $this->typeMaintenance = $typeMaintenance;
        return $this;
    }

    public function isDsi(): bool
    {
        return $this->typeMaintenance === 'DSI';
    }

    public function isPrestataire(): bool
    {
        return $this->typeMaintenance === 'Prestataire';
    }

    public function getPrestataireNom(): ?string
    {
        return $this->prestataireNom;
    }

    public function setPrestataireNom(?string $prestataireNom): static
    {
        $this->prestataireNom = $prestataireNom;
        return $this;
    }

    public function getNumeroBon(): ?string
    {
        return $this->numeroBon;
    }

    public function setNumeroBon(?string $numeroBon): static
    {
        $this->numeroBon = $numeroBon;
        return $this;
    }

    public function getDateRetourPrevue(): ?\DateTimeInterface
    {
        return $this->dateRetourPrevue;
    }

    public function setDateRetourPrevue(?\DateTimeInterface $dateRetourPrevue): static
    {
        $this->dateRetourPrevue = $dateRetourPrevue;
        return $this;
    }
}
