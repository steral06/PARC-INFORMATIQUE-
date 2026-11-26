<?php

namespace App\Repository;

use App\Entity\ChecklistPreparation;
use App\Entity\Equipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ChecklistPreparation>
 */
class ChecklistPreparationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChecklistPreparation::class);
    }

    /**
     * Retourne toutes les préparations d'un équipement, de la plus récente à la plus ancienne.
     *
     * @return ChecklistPreparation[]
     */
    public function findByEquipement(Equipement $equipement): array
    {
        return $this->createQueryBuilder('cp')
            ->where('cp.equipement = :eq')
            ->setParameter('eq', $equipement)
            ->orderBy('cp.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne la préparation en cours pour un équipement, ou null.
     */
    public function findEnCours(Equipement $equipement): ?ChecklistPreparation
    {
        return $this->createQueryBuilder('cp')
            ->where('cp.equipement = :eq')
            ->andWhere('cp.statut = :statut')
            ->setParameter('eq', $equipement)
            ->setParameter('statut', ChecklistPreparation::STATUT_EN_COURS)
            ->orderBy('cp.dateDebut', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}

