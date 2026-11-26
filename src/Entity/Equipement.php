<?php

namespace App\Entity;

use App\Repository\EquipementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EquipementRepository::class)]
#[ORM\Table(name: 'equipement')]
#[UniqueEntity(fields: ['numeroSerie'], ignoreNull: true, message: 'Ce numéro de série existe déjà dans le parc.')]
class Equipement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank(message: 'La désignation de l\'équipement est obligatoire.')]
    #[Assert\Length(max: 150, maxMessage: 'La désignation ne peut dépasser 150 caractères.')]
    private ?string $nom = null;

    #[ORM\Column(length: 100, unique: true, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'Le numéro de série ne peut dépasser 100 caractères.')]
    private ?string $numeroSerie = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50, maxMessage: 'Le code inventaire ne peut dépasser 50 caractères.')]
    private ?string $tagInventaire = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100, maxMessage: 'La marque ne peut dépasser 100 caractères.')]
    private ?string $marque = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le modèle est obligatoire.')]
    #[Assert\Length(max: 100, maxMessage: 'Le modèle ne peut dépasser 100 caractères.')]
    private ?string $modele = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['En service', 'En stock', 'En panne', 'Au rebut', 'Prêté'], message: 'Statut opérationnel invalide.')]
    private ?string $statut = 'En service'; // En service, En stock, En panne, Au rebut, Prêté

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateAcquisition = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $finGarantie = null;

    #[ORM\Column(nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le prix d\'achat doit être supérieur ou égal à zéro.')]
    private ?float $prixAchat = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Assert\NotNull(message: 'La catégorie de l\'équipement est obligatoire.')]
    private ?CategorieEquipement $categorie = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Lieu $lieu = null;

    #[ORM\Column(length: 45, nullable: true)]
    #[Assert\Ip(message: 'Veuillez saisir une adresse IP valide (IPv4 ou IPv6).')]
    private ?string $adresseIp = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $systemeExploitation = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateAjout = null;

    public function __construct()
    {
        $this->dateAjout = new \DateTimeImmutable();
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

    public function getNumeroSerie(): ?string
    {
        return $this->numeroSerie;
    }

    public function setNumeroSerie(?string $numeroSerie): static
    {
        $this->numeroSerie = $numeroSerie !== null && trim($numeroSerie) !== '' ? trim($numeroSerie) : null;
        return $this;
    }

    public function getTagInventaire(): ?string
    {
        return $this->tagInventaire;
    }

    public function setTagInventaire(?string $tagInventaire): static
    {
        $this->tagInventaire = $tagInventaire;
        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(?string $marque): static
    {
        $this->marque = $marque !== null && trim($marque) !== '' ? trim($marque) : null;
        return $this;
    }

    public function getModele(): ?string
    {
        return $this->modele;
    }

    public function setModele(string $modele): static
    {
        $this->modele = $modele;
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

    public function getDateAcquisition(): ?\DateTimeInterface
    {
        return $this->dateAcquisition;
    }

    public function setDateAcquisition(?\DateTimeInterface $dateAcquisition): static
    {
        $this->dateAcquisition = $dateAcquisition;
        return $this;
    }

    public function getFinGarantie(): ?\DateTimeInterface
    {
        return $this->finGarantie;
    }

    public function setFinGarantie(?\DateTimeInterface $finGarantie): static
    {
        $this->finGarantie = $finGarantie;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): static
    {
        $this->notes = $notes;
        return $this;
    }

    public function getCategorie(): ?CategorieEquipement
    {
        return $this->categorie;
    }

    public function setCategorie(?CategorieEquipement $categorie): static
    {
        $this->categorie = $categorie;
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

    public function getDateAjout(): ?\DateTimeImmutable
    {
        return $this->dateAjout;
    }

    public function setDateAjout(\DateTimeImmutable $dateAjout): static
    {
        $this->dateAjout = $dateAjout;
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

    public function getSystemeExploitation(): ?string
    {
        return $this->systemeExploitation;
    }

    public function setSystemeExploitation(?string $systemeExploitation): static
    {
        $this->systemeExploitation = $systemeExploitation !== null && trim($systemeExploitation) !== '' ? trim($systemeExploitation) : null;
        return $this;
    }

    /**
     * Indique si cet équipement est éligible à la procédure de préparation/vérification.
     * La préparation concerne uniquement les ordinateurs (portables, fixes, unités centrales, bureaux)
     * ayant le statut "En stock" ou "En service".
     * Les onduleurs, écrans, imprimantes, serveurs ou équipements réseau ne sont pas concernés.
     */
    public function isEligiblePreparation(): bool
    {
        if (!in_array($this->statut, ['En stock', 'En service'], true)) {
            return false;
        }

        if (!$this->categorie || !$this->categorie->getNomCat()) {
            return false;
        }

        $norm = mb_strtolower($this->categorie->getNomCat());

        // Doit être un ordinateur
        $isComputer = str_contains($norm, 'portable')
            || str_contains($norm, 'laptop')
            || str_contains($norm, 'fixe')
            || str_contains($norm, 'centrale')
            || str_contains($norm, 'bureau')
            || str_contains($norm, 'desktop')
            || str_contains($norm, 'ordinateur');

        // Exclusions strictes : onduleurs, imprimantes, écrans, commutateurs, serveurs
        $isExcluded = str_contains($norm, 'onduleur')
            || str_contains($norm, 'imprimante')
            || str_contains($norm, 'scanner')
            || str_contains($norm, 'écran')
            || str_contains($norm, 'ecran')
            || str_contains($norm, 'réseau')
            || str_contains($norm, 'reseau')
            || str_contains($norm, 'serveur');

        return $isComputer && !$isExcluded;
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
        $parts = [$this->nom ?? 'Équipement'];
        if ($this->marque) {
            $parts[] = $this->marque;
        }
        if ($this->numeroSerie) {
            $parts[] = '(' . $this->numeroSerie . ')';
        }
        return implode(' - ', $parts);
    }
}
