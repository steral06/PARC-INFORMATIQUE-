<?php

namespace App\Form;

use App\Entity\CategorieEquipement;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class CategorieEquipementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom_cat', TextType::class, [
                'label' => 'Nom de la catégorie',
                'attr' => ['placeholder' => 'Ex: Ordinateurs Portables, Onduleurs...'],
                'constraints' => [new NotBlank(message: 'Le nom de la catégorie est obligatoire')],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Description ou usage de cette famille d\'équipements...'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => CategorieEquipement::class,
        ]);
    }
}
