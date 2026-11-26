<?php

namespace App\Entity;

use App\Repository\ChecklistPreparationRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Mapping\OrderBy;

/**
 * Session de préparation/vérification d'un équipement.
 * Créée lors du clic sur le bouton "Préparer" de la fiche équipement.
 * Persiste l'état de chaque étape pour traçabilité et historique.
 */
#[ORM\Entity(repositoryClass: ChecklistPreparationRepository::class)]
#[ORM\Table(name: 'checklist_preparation')]
class ChecklistPreparation
{
    public const STATUT_EN_COURS  = 'en_cours';
    public const STATUT_TERMINEE  = 'terminee';
    public const STATUT_ABANDONNEE = 'abandonnee';

    /** Type de checklist selon le statut de l'équipement au moment du démarrage */
    public const TYPE_STOCK   = 'stock';
    public const TYPE_SERVICE = 'service';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Equipement $equipement = null;

    /** Technicien qui a initié la préparation */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $technicien = null;

    /** stock | service */
    #[ORM\Column(length: 20)]
    private string $type = self::TYPE_STOCK;

    /** en_cours | terminee | abandonnee */
    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_COURS;

    #[ORM\Column]
    private \DateTimeImmutable $dateDebut;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observations = null;

    /** @var Collection<int, ChecklistEtape> */
    #[ORM\OneToMany(
        targetEntity: ChecklistEtape::class,
        mappedBy: 'preparation',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
        fetch: 'EAGER'
    )]
    #[OrderBy(['ordre' => 'ASC'])]
    private Collection $etapes;

    public function __construct()
    {
        $this->dateDebut = new \DateTimeImmutable();
        $this->etapes    = new ArrayCollection();
    }

    /* ── Accesseurs ──────────────────────────────────────────── */

    public function getId(): ?int { return $this->id; }

    public function getEquipement(): ?Equipement { return $this->equipement; }
    public function setEquipement(?Equipement $equipement): static
    {
        $this->equipement = $equipement;
        return $this;
    }

    public function getTechnicien(): ?User { return $this->technicien; }
    public function setTechnicien(?User $technicien): static
    {
        $this->technicien = $technicien;
        return $this;
    }

    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getDateDebut(): \DateTimeImmutable { return $this->dateDebut; }

    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(?\DateTimeImmutable $dateFin): static { $this->dateFin = $dateFin; return $this; }

    public function getObservations(): ?string { return $this->observations; }
    public function setObservations(?string $observations): static { $this->observations = $observations; return $this; }

    /** @return Collection<int, ChecklistEtape> */
    public function getEtapes(): Collection { return $this->etapes; }

    public function addEtape(ChecklistEtape $etape): static
    {
        if (!$this->etapes->contains($etape)) {
            $this->etapes->add($etape);
            $etape->setPreparation($this);
        }
        return $this;
    }

    public function removeEtape(ChecklistEtape $etape): static
    {
        if ($this->etapes->removeElement($etape)) {
            if ($etape->getPreparation() === $this) {
                $etape->setPreparation(null);
            }
        }
        return $this;
    }

    /* ── Méthodes calculées ───────────────────────────────────── */

    public function getNbEtapesTotal(): int
    {
        return $this->etapes->count();
    }

    public function getNbEtapesOk(): int
    {
        return $this->etapes->filter(
            fn(ChecklistEtape $e) => $e->getStatut() === ChecklistEtape::STATUT_OK
        )->count();
    }

    public function getNbEtapesKo(): int
    {
        return $this->etapes->filter(
            fn(ChecklistEtape $e) => $e->getStatut() === ChecklistEtape::STATUT_KO
        )->count();
    }

    public function getTauxProgression(): int
    {
        $total = $this->getNbEtapesTotal();
        if ($total === 0) return 0;
        return (int) round(($this->getNbEtapesOk() + $this->getNbEtapesKo()) / $total * 100);
    }

    public function isTerminee(): bool
    {
        return $this->statut === self::STATUT_TERMINEE;
    }

    public function isEnCours(): bool
    {
        return $this->statut === self::STATUT_EN_COURS;
    }

    public function getLibelleType(): string
    {
        return $this->type === self::TYPE_SERVICE ? 'En service' : 'En stock';
    }
}

