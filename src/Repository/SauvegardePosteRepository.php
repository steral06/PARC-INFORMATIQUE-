<?php

namespace App\Repository;

use App\Entity\Equipement;
use App\Entity\SauvegardePoste;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SauvegardePoste>
 */
class SauvegardePosteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SauvegardePoste::class);
    }

    /**
     * Retourne la dernière opération d'un type donné pour un équipement
     */
    public function findDerniereOperation(Equipement $equipement, string $typeOperation): ?SauvegardePoste
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.equipement = :equipement')
            ->andWhere('s.typeOperation = :type')
            ->setParameter('equipement', $equipement)
            ->setParameter('type', $typeOperation)
            ->orderBy('s.dateExecution', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Retourne l'historique récent des sauvegardes de postes
     */
    public function findHistoriqueRecent(int $limit = 50): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.equipement', 'e')
            ->addSelect('e')
            ->orderBy('s.dateExecution', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

