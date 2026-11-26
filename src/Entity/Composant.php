<?php

namespace App\Entity;

use App\Repository\ComposantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ComposantRepository::class)]
#[ORM\Table(name: 'composant')]
class Composant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    private ?string $type = 'Mémoire RAM'; // Mémoire RAM, Disque SSD/HDD, Processeur, Alimentation, Toner/Consommable, Périphérique, Carte Réseau

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $numeroSerie = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $quantite = 1;

    #[ORM\Column(length: 50)]
    private ?string $statut = 'En stock'; // En stock, Installé, Défectueux

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Equipement $equipement = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Lieu $lieu = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateAcquisition = null;

    #[ORM\Column(nullable: true)]
    private ?float $prixAchat = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $marque = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $modele = null;

    /** Capacité (ex: "16 Go", "512 Go", "500W") */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $capacite = null;

    /** Fréquence / Vitesse (ex: "DDR4 3200MHz", "3.5 GHz") */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $frequence = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateAjout = null;

    public function __construct()
    {
        $this->dateAjout = new \DateTimeImmutable();
        $this->quantite = 1;
        $this->statut = 'En stock';
    }

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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getNumeroSerie(): ?string
    {
        return $this->numeroSerie;
    }

    public function setNumeroSerie(?string $numeroSerie): static
    {
        $this->numeroSerie = $numeroSerie;
        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;
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

    public function getEquipement(): ?Equipement
    {
        return $this->equipement;
    }

    public function setEquipement(?Equipement $equipement): static
    {
        $this->equipement = $equipement;
        return $this;
    }

    public function getLieu(): ?Lieu
    {
        return $this->lieu;
    }

    public function setLieu(?Lieu $lieu): static
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getDateAcquisition(): ?\DateTimeInterface
    {
        return $this->dateAcquisition;
    }

    public function setDateAcquisition(?\DateTimeInterface $dateAcquisition): static
    {
        $this->dateAcquisition = $dateAcquisition;
        return $this;
    }

    public function getPrixAchat(): ?float
    {
        return $this->prixAchat;
    }

    public function setPrixAchat(?float $prixAchat): static
    {
        $this->prixAchat = $prixAchat;
        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(?string $marque): static
    {
        $this->marque = $marque;
        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(?string $modele): static
    {
        $this->modele = $modele;
        return $this;
    }

    public function getCapacite(): ?string
    {
        return $this->capacite;
    }

    public function setCapacite(?string $capacite): static
    {
        $this->capacite = $capacite;
        return $this;
    }

    public function getFrequence(): ?string
    {
        return $this->frequence;
    }

    public function setFrequence(?string $frequence): static
    {
        $this->frequence = $frequence;
        return $this;
    }

    /**
     * Retourne une description courte automatique basée sur les champs structurés.
     * Ex: "Kingston Fury — 16 Go DDR4 3200MHz"
     */
    public function getDescriptionAuto(): string
    {
        $parts = array_filter([
            $this->marque,
            $this->modele,
            $this->capacite,
            $this->frequence,
        ]);
        return implode(' — ', $parts) ?: ($this->nom ?? '');
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getDateAjout(): ?\DateTimeImmutable
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTimeImmutable $dateAjout): static
    {
        $this->dateAjout = $dateAjout;
        return $this;
    }

    public function getAgeAnnees(): ?float
    {
        if (!$this->dateAcquisition) {
            return null;
        }
        $now = new \DateTime();
        $diff = $now->diff($this->dateAcquisition);
        return round($diff->y + ($diff->m / 12), 1);
    }

    public function isVieillissant(): bool
    {
        $age = $this->getAgeAnnees();
        return $age !== null && $age >= 3.0 && $age < 5.0;
    }

    public function isObsolete(): bool
    {
        $age = $this->getAgeAnnees();
        return $age !== null && $age >= 5.0;
    }

    public function __toString(): string
    {
        return sprintf('%s [%s]', $this->nom, $this->type);
    }
}
