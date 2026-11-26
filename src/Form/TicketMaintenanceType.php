<?php

namespace App\Form;

use App\Entity\Equipement;
use App\Entity\TicketMaintenance;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class TicketMaintenanceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('typeMaintenance', ChoiceType::class, [
                'label' => 'Type de prise en charge',
                'choices' => [
                    '⚡ Intervention Interne (DSI)' => 'DSI',
                    '🏢 Maintenance Externe (Prestataire)' => 'Prestataire',
                ],
                'expanded' => true,
                'multiple' => false,
            ])
            ->add('equipement', EntityType::class, [
                'class' => Equipement::class,
                'choice_label' => function (Equipement $eq) {
                    return sprintf('%s [%s] - (%s)', $eq->getNom(), $eq->getMarque(), $eq->getNumeroSerie());
                },
                'label' => 'Matériel concerné',
                'placeholder' => '-- Sélectionner l\'équipement --',
                'constraints' => [new NotBlank(message: 'L\'équipement est obligatoire')],
            ])
            ->add('titre', TextType::class, [
                'label' => 'Titre / Panne constatée',
                'attr' => ['placeholder' => 'Ex: Écran noir au démarrage, Alimentation grillée, Disque 100%...'],
                'constraints' => [new NotBlank(message: 'Le titre est obligatoire')],
            ])
            ->add('priorite', ChoiceType::class, [
                'label' => 'Niveau d\'urgence',
                'choices' => [
                    'Basse' => 'Basse',
                    'Moyenne' => 'Moyenne',
                    'Haute' => 'Haute',
                    'Critique' => 'Critique',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut du ticket',
                'choices' => [
                    'Nouveau' => 'Nouveau',
                    'En cours' => 'En cours',
                    'Résolu' => 'Résolu',
                    'Annulé' => 'Annulé',
                ],
            ])
            ->add('technicien', TextType::class, [
                'label' => 'Technicien DSI en charge',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Support DSI, Technicien Support...'],
            ])
            ->add('prestataireNom', TextType::class, [
                'label' => 'Société / Prestataire externe',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Dell Support Pro, HP SAV, ReparTech SARL...'],
            ])
            ->add('numeroBon', TextType::class, [
                'label' => 'N° Devis / Bon de commande / RMA',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: DEV-2026-084, RMA-8849...'],
            ])
            ->add('dateRetourPrevue', DateType::class, [
                'label' => 'Date estimée de retour atelier',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('cout', NumberType::class, [
                'label' => 'Coût de réparation (FCFA)',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: 45000'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Symptômes observés & Circonstances',
                'attr' => ['rows' => 3, 'placeholder' => 'Décrivez le problème constaté ou collez le diagnostic de l\'assistant...'],
                'constraints' => [new NotBlank(message: 'La description est obligatoire')],
            ])
            ->add('solution', TextareaType::class, [
                'label' => 'Action effectuée / Solution appliquée',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Détail de la réparation, pièces changées, commandes exécutées...'],
            ])
            ->add('dateResolution', DateTimeType::class, [
                'label' => 'Date & Heure de clôture',
                'widget' => 'single_text',
                'required' => false,
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => TicketMaintenance::class,
        ]);
    }
}
