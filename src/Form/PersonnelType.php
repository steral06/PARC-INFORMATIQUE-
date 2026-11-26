<?php

namespace App\Form;

use App\Entity\Personnel;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class PersonnelType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('matricule', TextType::class, [
                'label' => 'Matricule / Identifiant',
                'attr' => [
                    'placeholder' => 'Ex: PROD-0058, SANTE-0012...',
                    'autocomplete' => 'off',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le matricule est obligatoire'),
                ],
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom & Prénoms complets',
                'attr' => [
                    'placeholder' => 'Ex: Jean-Luc KOFFI, Sarah MENSAH...',
                ],
                'constraints' => [
                    new NotBlank(message: 'Le nom est obligatoire'),
                ],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Catégorie / Statut dans l\'entreprise',
                'choices' => [
                    '👔 Employé (Salarié CDI/CDD)' => Personnel::TYPE_EMPLOYE,
                    '💼 Prestataire / Consultant externe' => Personnel::TYPE_PRESTATAIRE,
                    '🎓 Stagiaire / Alternant' => Personnel::TYPE_STAGIAIRE,
                    '🏷️ Visiteur / Invité temporaire' => Personnel::TYPE_VISITEUR,
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('service', TextType::class, [
                'label' => 'Service / Direction d\'affectation',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: PRODUCTION, SANTE, SINISTRE, DSI...',
                    'list' => 'services_datalist',
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse Email professionnelle',
                'required' => false,
                'attr' => [
                    'placeholder' => 'prenom.nom@gtac2a-iardt.com',
                ],
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Numéro de Téléphone / Flotte',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Ex: +228 90 00 00 00, Poste 405...',
                ],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut opérationnel',
                'choices' => [
                    '🟢 Actif' => 'Actif',
                    '⚪ Inactif / Parti' => 'Inactif',
                    '🟡 Suspendu' => 'Suspendu',
                ],
                'attr' => [
                    'class' => 'form-select',
                ],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notes & Remarques (interne)',
                'required' => false,
                'attr' => [
                    'rows' => 3,
                    'placeholder' => 'Détails de mission, tuteur, société externe, date de fin...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Personnel::class,
        ]);
    }
}

