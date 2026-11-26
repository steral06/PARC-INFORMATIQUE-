<?php

namespace App\Form;

use App\Entity\Attribution;
use App\Entity\Equipement;
use App\Entity\Personnel;
use App\Repository\AttributionRepository;
use App\Repository\PersonnelRepository;
use App\Service\AttributionManager;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;

class AttributionType extends AbstractType
{
    public function __construct(
        private readonly AttributionRepository $attributionRepository,
        private readonly PersonnelRepository $personnelRepository,
        private readonly AttributionManager $attributionManager,
    ) {}

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Pre-build the map once: equipement_id => current beneficiary name
        $holderMap = $this->attributionRepository->findActiveAttributeeMap();

        $builder
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
                'choice_attr' => function (Equipement $eq) {
                    return [
                        'data-family' => $this->attributionManager->getCategoryFamily($eq->getCategorie()),
                        'data-cat-nom' => $eq->getCategorie()?->getNomCat() ?? 'Sans catégorie',
                        'data-nom' => $eq->getNom(),
                        'data-sn' => $eq->getNumeroSerie() ?? $eq->getTagInventaire() ?? '',
                    ];
                },
                'label' => 'Équipement concerné',
                'placeholder' => '-- Sélectionner l\'équipement --',
                'constraints' => [new NotBlank(message: 'L\'équipement est obligatoire')],
            ])
            ->add('personnel', EntityType::class, [
                'class' => Personnel::class,
                'query_builder' => function (PersonnelRepository $repo) {
                    return $repo->createQueryBuilder('p')
                        ->where('p.statut = :statut')
                        ->setParameter('statut', 'Actif')
                        ->orderBy('p.type', 'ASC')
                        ->addOrderBy('p.nom', 'ASC');
                },
                'choice_label' => function (Personnel $p) {
                    return sprintf('%s — %s (%s)', $p->getNom(), $p->getMatricule(), $p->getService() ?? 'Sans service');
                },
                'group_by' => function (Personnel $p) {
                    return match ($p->getType()) {
                        'Employé' => '👔 Employés (Salariés)',
                        'Prestataire' => '💼 Prestataires & Consultants',
                        'Stagiaire' => '🎓 Stagiaires & Alternants',
                        'Visiteur' => '🏷️ Visiteurs',
                        default => '👤 Autre Personnel',
                    };
                },
                'choice_attr' => function (Personnel $p) {
                    $activeDevices = [];
                    foreach ($p->getAttributions() as $att) {
                        if ($att->getStatut() === 'En cours' && $att->getEquipement()) {
                            $eq = $att->getEquipement();
                            $activeDevices[] = [
                                'id' => $eq->getId(),
                                'nom' => $eq->getNom(),
                                'sn' => $eq->getNumeroSerie() ?? $eq->getTagInventaire() ?? 'S/N non renseigné',
                                'catNom' => $eq->getCategorie()?->getNomCat() ?? 'Équipement',
                                'family' => $this->attributionManager->getCategoryFamily($eq->getCategorie()),
                                'attributionId' => $att->getId(),
                            ];
                        }
                    }

                    return [
                        'data-nom' => $p->getNom(),
                        'data-service' => $p->getService() ?? '',
                        'data-matricule' => $p->getMatricule(),
                        'data-type' => $p->getType(),
                        'data-email' => $p->getEmail() ?? '',
                        'data-telephone' => $p->getTelephone() ?? '',
                        'data-active-atts' => (string) count($activeDevices),
                        'data-devices' => json_encode($activeDevices, JSON_HEX_APOS | JSON_HEX_QUOT),
                    ];
                },
                'label' => 'Personnel bénéficiaire (Par défaut)',
                'placeholder' => '-- Choisir un personnel dans la liste GTA --',
                'required' => false,
            ])
            ->add('beneficiaireNom', TextType::class, [
                'label' => 'Nom du Bénéficiaire',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Koffi Mensah'],
            ])
            ->add('beneficiaireService', TextType::class, [
                'label' => 'Service / Direction',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Direction Financière, Agence Port...'],
            ])
            ->add('dateDebut', DateTimeType::class, [
                'label' => 'Date de remise',
                'widget' => 'single_text',
            ])
            ->add('dateFinPrevue', DateTimeType::class, [
                'label' => 'Date de retour prévue',
                'widget' => 'single_text',
                'required' => false,
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En cours' => 'En cours',
                    'Restitué' => 'Restitué',
                    'Prolongé' => 'Prolongé',
                ],
            ])
            ->add('motif', TextareaType::class, [
                'label' => 'Motif',
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Ex: Dotation poste, Remplacement...'],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Observations',
                'required' => false,
                'attr' => ['rows' => 2, 'placeholder' => 'Observations éventuelles...'],
            ])
        ;

        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            $data = $event->getData();
            if (!empty($data['personnel'])) {
                $personnel = $this->personnelRepository->find($data['personnel']);
                if ($personnel) {
                    if (empty($data['beneficiaireNom'])) {
                        $data['beneficiaireNom'] = $personnel->getNom();
                    }
                    if (empty($data['beneficiaireService']) && $personnel->getService()) {
                        $data['beneficiaireService'] = $personnel->getService();
                    }
                    $event->setData($data);
                }
            }
        });

        $builder->addEventListener(FormEvents::POST_SUBMIT, function (FormEvent $event) {
            /** @var Attribution|null $data */
            $data = $event->getData();
            if ($data && $data->getPersonnel()) {
                $p = $data->getPersonnel();
                if (!$data->getBeneficiaireNom()) {
                    $data->setBeneficiaireNom($p->getNom());
                }
                if (!$data->getBeneficiaireService() && $p->getService()) {
                    $data->setBeneficiaireService($p->getService());
                }
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Attribution::class,
        ]);
    }
}
