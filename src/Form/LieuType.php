<?php

namespace App\Form;

use App\Entity\Lieu;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class LieuType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_lieu', TextType::class, [
                'label' => 'Nom de l\'emplacement',
                'attr' => ['placeholder' => 'Ex: GTAC2A-IARDT Siège - Direction Générale, Agence Tokoin...'],
                'constraints' => [new NotBlank(message: 'Le nom du lieu est obligatoire')],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description / Précisions',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Bâtiment, étage, bureau, consignes d\'accès...'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lieu::class,
        ]);
    }
}
