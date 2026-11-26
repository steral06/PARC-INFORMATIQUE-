<?php

namespace App\Form;

use App\Entity\Composant;
use App\Entity\Equipement;
use App\Entity\Lieu;
use App\Repository\AttributionRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\PositiveOrZero;

class ComposantType extends AbstractType
{
    public function __construct(
        private readonly AttributionRepository $attributionRepository,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Pre-build the map once: equipement_id => current beneficiary name
        $holderMap = $this->attributionRepository->findActiveAttributeeMap();

        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Catégorie',
                'choices' => [
                    'Mémoire RAM' => 'Mémoire RAM',
                    'Disque SSD / Disque Dur' => 'Disque SSD/HDD',
                    'Processeur (CPU)' => 'Processeur',
                    'Carte Graphique / Vidéo' => 'Carte graphique',
                    'Alimentation / Batterie / Chargeur' => 'Alimentation',
                    'Toner / Cartouche d\'encre' => 'Toner/Consommable',
                    'Carte Réseau / Câblage / Wi-Fi' => 'Réseau',
                    'Périphérique & Accessoire' => 'Périphérique',
                    'Autre' => 'Autre',
                ],
                'attr' => ['id' => 'composant_type_field'],
            ])
            ->add('marque', TextType::class, [
                'label' => 'Marque',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Kingston, Samsung, Logitech...'],
            ])
            ->add('modele', TextType::class, [
                'label' => 'Modèle / Référence',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Fury Beast, 870 EVO, MK220...'],
            ])
            ->add('capacite', TextType::class, [
                'label' => 'Capacité',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 16 Go, 512 Go, 500W...'],
            ])
            ->add('frequence', TextType::class, [
                'label' => 'Fréquence / Vitesse',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: DDR4 3200MHz, 3.5 GHz...'],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Désignation complémentaire',
                'required' => false,
                'attr' => ['placeholder' => 'Précision optionnelle (auto-générée si vide)'],
            ])
            ->add('numeroSerie', TextType::class, [
                'label' => 'Numéro de série (S/N)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: SN-RAM-2026-9938'],
            ])
            ->add('quantite', IntegerType::class, [
                'label' => 'Quantité',
                'attr' => ['min' => 0],
                'constraints' => [new PositiveOrZero(message: 'La quantité doit être positive ou nulle')],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En stock' => 'En stock',
                    'Installé' => 'Installé',
                    'Défectueux' => 'Défectueux',
                    'Au rebut' => 'Rebut',
                ],
            ])
            ->add('equipement', EntityType::class, [
                'class' => Equipement::class,
                'choice_label' => function (Equipement $eq) use ($holderMap) {
                    $holder = $holderMap[$eq->getId()] ?? null;
                    if ($holder !== null) {
                        $info = $holder;
                    } else {
                        $info = trim(($eq->getMarque() ?? '') . ' ' . ($eq->getModele() ?? '')) ?: 'En stock';
                    }
                    return sprintf('%s [%s] - (%s)', $eq->getNom(), $eq->getTagInventaire() ?? $eq->getNumeroSerie() ?? '—', $info);
                },
                'required' => false,
                'placeholder' => '-- Aucun (Pièce en stock) --',
                'label' => 'Équipement associé',
            ])
            ->add('lieu', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'nomLieu',
                'required' => false,
                'placeholder' => '-- Choisir un emplacement --',
                'label' => 'Emplacement',
            ])
            ->add('dateAcquisition', DateType::class, [
                'label' => 'Date d\'achat',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('prixAchat', NumberType::class, [
                'label' => 'Prix d\'achat unitaire (FCFA)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 45000'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes & Spécifications',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Compatibilité, fréquence, garantie...'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Composant::class,
        ]);
    }
}
