<?php

namespace App\Entity;

use App\Repository\LigneInventaireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: LigneInventaireRepository::class)]
#[ORM\Table(name: 'ligne_inventaire')]
class LigneInventaire
{
    public const STATUT_EN_ATTENTE   = 'En attente';
    public const STATUT_CONFORME     = 'Conforme';
    public const STATUT_DEPLACE      = 'Déplacé';
    public const STATUT_MANQUANT     = 'Manquant';
    public const STATUT_HORS_SERVICE = 'Hors service';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_CONFORME,
        self::STATUT_DEPLACE,
        self::STATUT_MANQUANT,
        self::STATUT_HORS_SERVICE,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'lignes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?CampagneInventaire $campagne = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipement $equipement = null;

    #[ORM\Column(length: 50)]
    private string $statutConstate = self::STATUT_EN_ATTENTE;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $lieuAttendu = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Lieu $lieuConstate = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $datePointage = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pointePar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observations = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCampagne(): ?CampagneInventaire
    {
        return $this->campagne;
    }

    public function setCampagne(?CampagneInventaire $campagne): static
    {
        $this->campagne = $campagne;
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

    public function getStatutConstate(): string
    {
        return $this->statutConstate;
    }

    public function setStatutConstate(string $statutConstate): static
    {
        $this->statutConstate = $statutConstate;
        return $this;
    }

    public function getLieuAttendu(): ?string
    {
        return $this->lieuAttendu;
    }

    public function setLieuAttendu(?string $lieuAttendu): static
    {
        $this->lieuAttendu = $lieuAttendu;
        return $this;
    }

    public function getLieuConstate(): ?Lieu
    {
        return $this->lieuConstate;
    }

    public function setLieuConstate(?Lieu $lieuConstate): static
    {
        $this->lieuConstate = $lieuConstate;
        return $this;
    }

    public function getDatePointage(): ?\DateTimeInterface
    {
        return $this->datePointage;
    }

    public function setDatePointage(?\DateTimeInterface $datePointage): static
    {
        $this->datePointage = $datePointage;
        return $this;
    }

    public function getPointePar(): ?string
    {
        return $this->pointePar;
    }

    public function setPointePar(?string $pointePar): static
    {
        $this->pointePar = $pointePar;
        return $this;
    }

    public function getObservations(): ?string
    {
        return $this->observations;
    }

    public function setObservations(?string $observations): static
    {
        $this->observations = $observations;
        return $this;
    }

    public function isEcart(): bool
    {
        return in_array($this->statutConstate, [
            self::STATUT_DEPLACE,
            self::STATUT_MANQUANT,
            self::STATUT_HORS_SERVICE,
        ], true);
    }
}

