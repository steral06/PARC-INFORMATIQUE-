<?php

namespace App\Entity;

use App\Repository\AttributionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AttributionRepository::class)]
#[ORM\Table(name: 'attribution')]
class Attribution
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Assert\NotNull(message: 'L\'équipement à attribuer est obligatoire.')]
    private ?Equipement $equipement = null;

    #[ORM\ManyToOne(targetEntity: Personnel::class, inversedBy: 'attributions')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Personnel $personnel = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'Le nom du bénéficiaire est obligatoire.')]
    #[Assert\Length(max: 150, maxMessage: 'Le nom du bénéficiaire ne peut dépasser 150 caractères.')]
    private ?string $beneficiaireNom = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Le service ne peut dépasser 100 caractères.')]
    private ?string $beneficiaireService = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Assert\NotNull(message: 'La date de début est obligatoire.')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFinPrevue = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateRestitution = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['En cours', 'Restitué', 'Prolongé'], message: 'Statut d\'attribution invalide.')]
    private ?string $statut = 'En cours'; // En cours, Restitué, Prolongé

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->dateDebut = new \DateTime();
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

    public function getPersonnel(): ?Personnel
    {
        return $this->personnel;
    }

    public function setPersonnel(?Personnel $personnel): static
    {
        $this->personnel = $personnel;
        if ($personnel !== null) {
            $this->beneficiaireNom = $personnel->getNom();
            if ($personnel->getService()) {
                $this->beneficiaireService = $personnel->getService();
            }
        }
        return $this;
    }

    public function getBeneficiaireNom(): ?string
    {
        return $this->beneficiaireNom;
    }

    public function setBeneficiaireNom(string $beneficiaireNom): static
    {
        $this->beneficiaireNom = $beneficiaireNom;
        return $this;
    }

    public function getBeneficiaireService(): ?string
    {
        return $this->beneficiaireService;
    }

    public function setBeneficiaireService(?string $beneficiaireService): static
    {
        $this->beneficiaireService = $beneficiaireService;
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

    public function getDateFinPrevue(): ?\DateTimeInterface
    {
        return $this->dateFinPrevue;
    }

    public function setDateFinPrevue(?\DateTimeInterface $dateFinPrevue): static
    {
        $this->dateFinPrevue = $dateFinPrevue;
        return $this;
    }

    public function getDateRestitution(): ?\DateTimeInterface
    {
        return $this->dateRestitution;
    }

    public function setDateRestitution(?\DateTimeInterface $dateRestitution): static
    {
        $this->dateRestitution = $dateRestitution;
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

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;
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
}
