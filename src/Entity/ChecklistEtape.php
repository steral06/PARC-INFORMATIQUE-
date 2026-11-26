<?php

namespace App\Entity;

use App\Repository\ChecklistEtapeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Une étape individuelle au sein d'une ChecklistPreparation.
 * Chaque étape correspond à une tâche précise (ex: installation d'Office).
 */
#[ORM\Entity(repositoryClass: ChecklistEtapeRepository::class)]
#[ORM\Table(name: 'checklist_etape')]
class ChecklistEtape
{
    public const STATUT_EN_ATTENTE = 'en_attente';
    public const STATUT_OK         = 'ok';
    public const STATUT_KO         = 'ko';

    /** Groupes fonctionnels pour l'affichage */
    public const GROUPE_SAUVEGARDE    = 'sauvegarde';
    public const GROUPE_INSTALLATION  = 'installation';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'etapes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?ChecklistPreparation $preparation = null;

    /** Identifiant technique de l'étape (ex: "sauvegarde_c", "office", "kaspersky") */
    #[ORM\Column(length: 80)]
    private string $cle = '';

    #[ORM\Column(length: 200)]
    private string $libelle = '';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    /** sauvegarde | installation */
    #[ORM\Column(length: 30)]
    private string $groupe = self::GROUPE_INSTALLATION;

    /** Ordre d'affichage dans la checklist */
    #[ORM\Column]
    private int $ordre = 0;

    /** en_attente | ok | ko */
    #[ORM\Column(length: 20)]
    private string $statut = self::STATUT_EN_ATTENTE;

    /** Remarques saisies par le technicien sur cette étape */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $observations = null;

    /** Horodatage du dernier changement de statut */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $dateValidation = null;

    /** Icône Bootstrap Icons (ex: "bi-windows", "bi-hdd") */
    #[ORM\Column(length: 60, nullable: true)]
    private ?string $icone = null;

    /* ── Accesseurs ──────────────────────────────────────────── */

    public function getId(): ?int { return $this->id; }

    public function getPreparation(): ?ChecklistPreparation { return $this->preparation; }
    public function setPreparation(?ChecklistPreparation $preparation): static
    {
        $this->preparation = $preparation;
        return $this;
    }

    public function getCle(): string { return $this->cle; }
    public function setCle(string $cle): static { $this->cle = $cle; return $this; }

    public function getLibelle(): string { return $this->libelle; }
    public function setLibelle(string $libelle): static { $this->libelle = $libelle; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }

    public function getGroupe(): string { return $this->groupe; }
    public function setGroupe(string $groupe): static { $this->groupe = $groupe; return $this; }

    public function getOrdre(): int { return $this->ordre; }
    public function setOrdre(int $ordre): static { $this->ordre = $ordre; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }

    public function getObservations(): ?string { return $this->observations; }
    public function setObservations(?string $observations): static { $this->observations = $observations; return $this; }

    public function getDateValidation(): ?\DateTimeImmutable { return $this->dateValidation; }
    public function setDateValidation(?\DateTimeImmutable $dateValidation): static
    {
        $this->dateValidation = $dateValidation;
        return $this;
    }

    public function getIcone(): ?string { return $this->icone; }
    public function setIcone(?string $icone): static { $this->icone = $icone; return $this; }

    /* ── Méthodes calculées ───────────────────────────────────── */

    public function isEnAttente(): bool { return $this->statut === self::STATUT_EN_ATTENTE; }
    public function isOk(): bool        { return $this->statut === self::STATUT_OK; }
    public function isKo(): bool        { return $this->statut === self::STATUT_KO; }
    public function isValide(): bool    { return !$this->isEnAttente(); }

    public function isSauvegarde(): bool   { return $this->groupe === self::GROUPE_SAUVEGARDE; }
    public function isInstallation(): bool { return $this->groupe === self::GROUPE_INSTALLATION; }
}

