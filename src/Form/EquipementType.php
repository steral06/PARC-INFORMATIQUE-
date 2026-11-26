<?php

namespace App\Form;

use App\Entity\CategorieEquipement;
use App\Entity\Equipement;
use App\Entity\Lieu;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class EquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Désignation',
                'attr' => ['placeholder' => 'Ex: Dell Latitude 5530'],
                'constraints' => [new NotBlank(message: 'Le nom est obligatoire')],
            ])
            ->add('numeroSerie', TextType::class, [
                'label' => 'Numéro de série (S/N)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: CN-0HG123-45678'],
            ])
            ->add('tagInventaire', TextType::class, [
                'label' => 'Code inventaire',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: INV-2026-042'],
            ])
            ->add('adresseIp', TextType::class, [
                'label' => 'Adresse IP',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 192.168.1.105'],
            ])
            ->add('systemeExploitation', TextType::class, [
                'label' => 'Système d\'exploitation (OS)',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Windows 11, Ubuntu 22.04 LTS...',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('marque', TextType::class, [
                'label' => 'Marque',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: Dell, HP, Lenovo, Canon, Epson...',
                    'autocomplete' => 'off',
                ],
            ])
            ->add('modele', TextType::class, [
                'label' => 'Modèle',
                'attr' => ['placeholder' => 'Ex: Latitude 5530'],
                'constraints' => [new NotBlank(message: 'Le modèle est obligatoire')],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En service' => 'En service',
                    'En stock' => 'En stock',
                    'En panne' => 'En panne',
                    'Prêté' => 'Prêté',
                    'Au rebut' => 'Au rebut',
                ],
            ])
            ->add('categorie', EntityType::class, [
                'class' => CategorieEquipement::class,
                'choice_label' => 'nom_cat',
                'label' => 'Catégorie',
                'placeholder' => '-- Choisir une catégorie --',
                'required' => true,
            ])
            ->add('lieu', EntityType::class, [
                'class' => Lieu::class,
                'choice_label' => 'nom_lieu',
                'label' => 'Emplacement',
                'placeholder' => '-- Choisir un emplacement --',
                'required' => false,
            ])
            ->add('dateAcquisition', DateType::class, [
                'label' => 'Date d\'acquisition',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('finGarantie', DateType::class, [
                'label' => 'Fin de garantie',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('prixAchat', NumberType::class, [
                'label' => 'Prix d\'achat (FCFA)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 650000'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes & Remarques',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Remarques éventuelles...'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Equipement::class,
        ]);
    }
}
