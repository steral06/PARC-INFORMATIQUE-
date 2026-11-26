<?php

namespace App\Entity;

use App\Repository\PersonnelRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PersonnelRepository::class)]
#[ORM\Table(name: 'personnel')]
#[ORM\Index(columns: ['matricule'], name: 'idx_personnel_matricule')]
#[ORM\Index(columns: ['type'], name: 'idx_personnel_type')]
#[ORM\Index(columns: ['service'], name: 'idx_personnel_service')]
#[UniqueEntity(fields: ['matricule'], message: 'Ce matricule est déjà attribué à un autre personnel.')]
class Personnel
{
    public const TYPE_EMPLOYE = 'Employé';
    public const TYPE_PRESTATAIRE = 'Prestataire';
    public const TYPE_STAGIAIRE = 'Stagiaire';
    public const TYPE_VISITEUR = 'Visiteur';

    public const TYPES = [
        self::TYPE_EMPLOYE,
        self::TYPE_PRESTATAIRE,
        self::TYPE_STAGIAIRE,
        self::TYPE_VISITEUR,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le matricule est obligatoire.')]
    private ?string $matricule = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le nom du personnel est obligatoire.')]
    private ?string $nom = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: self::TYPES, message: 'Type de personnel invalide.')]
    private string $type = self::TYPE_EMPLOYE;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(length: 150, nullable: true)]
    #[Assert\Email(message: 'Veuillez saisir une adresse email valide.')]
    private ?string $email = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 30)]
    private string $statut = 'Actif'; // Actif, Inactif, Suspendu

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateAjout;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    /**
     * @var Collection<int, Attribution>
     */
    #[ORM\OneToMany(mappedBy: 'personnel', targetEntity: Attribution::class)]
    private Collection $attributions;

    public function __construct()
    {
        $this->dateAjout = new \DateTime();
        $this->attributions = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(string $matricule): static
    {
        $this->matricule = strtoupper(trim($matricule));
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = trim($nom);
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getService(): ?string
    {
        return $this->service;
    }

    public function setService(?string $service): static
    {
        $this->service = $service ? trim($service) : null;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email ? strtolower(trim($email)) : null;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone ? trim($telephone) : null;
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

    public function getDateAjout(): \DateTimeInterface
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTimeInterface $dateAjout): static
    {
        $this->dateAjout = $dateAjout;
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
     * @return Collection<int, Attribution>
     */
    public function getAttributions(): Collection
    {
        return $this->attributions;
    }

    public function addAttribution(Attribution $attribution): static
    {
        if (!$this->attributions->contains($attribution)) {
            $this->attributions->add($attribution);
            $attribution->setPersonnel($this);
        }

        return $this;
    }

    public function removeAttribution(Attribution $attribution): static
    {
        if ($this->attributions->removeElement($attribution)) {
            if ($attribution->getPersonnel() === $this) {
                $attribution->setPersonnel(null);
            }
        }

        return $this;
    }

    /**
     * Retourne le nombre d'attributions actuellement actives
     */
    public function getActiveAttributionsCount(): int
    {
        $count = 0;
        foreach ($this->attributions as $attribution) {
            if ($attribution->getStatut() === 'En cours') {
                $count++;
            }
        }
        return $count;
    }

    public function __toString(): string
    {
        return sprintf('%s - %s (%s)', $this->matricule, $this->nom, $this->type);
    }
}

