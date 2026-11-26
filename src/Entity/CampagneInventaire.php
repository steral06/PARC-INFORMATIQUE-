<?php

namespace App\Entity;

use App\Repository\CampagneInventaireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CampagneInventaireRepository::class)]
#[ORM\Table(name: 'campagne_inventaire')]
class CampagneInventaire
{
    public const STATUT_EN_COURS = 'En cours';
    public const STATUT_CLOTUREE = 'Clôturée';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $annee = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateCloture = null;

    #[ORM\Column(length: 30)]
    private string $statut = self::STATUT_EN_COURS;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $creePar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * @var Collection<int, LigneInventaire>
     */
    #[ORM\OneToMany(mappedBy: 'campagne', targetEntity: LigneInventaire::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $lignes;

    public function __construct()
    {
        $this->dateDebut = new \DateTime();
        $this->annee = (int) date('Y');
        $this->lignes = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): static
    {
        $this->annee = $annee;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateCloture(): ?\DateTimeInterface
    {
        return $this->dateCloture;
    }

    public function setDateCloture(?\DateTimeInterface $dateCloture): static
    {
        $this->dateCloture = $dateCloture;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getCreePar(): ?string
    {
        return $this->creePar;
    }

    public function setCreePar(?string $creePar): static
    {
        $this->creePar = $creePar;
        return $this;
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

    /**
     * @return Collection<int, LigneInventaire>
     */
    public function getLignes(): Collection
    {
        return $this->lignes;
    }

    public function addLigne(LigneInventaire $ligne): static
    {
        if (!$this->lignes->contains($ligne)) {
            $this->lignes->add($ligne);
            $ligne->setCampagne($this);
        }
        return $this;
    }

    public function removeLigne(LigneInventaire $ligne): static
    {
        if ($this->lignes->removeElement($ligne)) {
            if ($ligne->getCampagne() === $this) {
                $ligne->setCampagne(null);
            }
        }
        return $this;
    }

    // ─── Indicateurs de progression ─────────────────────────────

    public function getTotalEquipements(): int
    {
        return $this->lignes->count();
    }

    public function getEquipementsControlesCount(): int
    {
        $count = 0;
        foreach ($this->lignes as $ligne) {
            if ($ligne->getStatutConstate() !== LigneInventaire::STATUT_EN_ATTENTE) {
                $count++;
            }
        }
        return $count;
    }

    public function getTauxProgression(): float
    {
        $total = $this->getTotalEquipements();
        if ($total === 0) {
            return 0.0;
        }
        return round(($this->getEquipementsControlesCount() / $total) * 100, 1);
    }

    public function getConformesCount(): int
    {
        $count = 0;
        foreach ($this->lignes as $ligne) {
            if ($ligne->getStatutConstate() === LigneInventaire::STATUT_CONFORME) {
                $count++;
            }
        }
        return $count;
    }

    public function getDeplacesCount(): int
    {
        $count = 0;
        foreach ($this->lignes as $ligne) {
            if ($ligne->getStatutConstate() === LigneInventaire::STATUT_DEPLACE) {
                $count++;
            }
        }
        return $count;
    }

    public function getManquantsCount(): int
    {
        $count = 0;
        foreach ($this->lignes as $ligne) {
            if ($ligne->getStatutConstate() === LigneInventaire::STATUT_MANQUANT) {
                $count++;
            }
        }
        return $count;
    }

    public function getHorsServiceCount(): int
    {
        $count = 0;
        foreach ($this->lignes as $ligne) {
            if ($ligne->getStatutConstate() === LigneInventaire::STATUT_HORS_SERVICE) {
                $count++;
            }
        }
        return $count;
    }

    public function getEnAttenteCount(): int
    {
        return $this->getTotalEquipements() - $this->getEquipementsControlesCount();
    }
}

