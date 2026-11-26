<?php

namespace App\Repository;

use App\Entity\Attribution;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attribution>
 */
class AttributionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attribution::class);
    }

    /**
     * Returns a map of equipement_id => beneficiaireNom for all active attributions.
     * Used to enrich equipment dropdowns with the current holder's name.
     *
     * @return array<int, string>
     */
    public function findActiveAttributeeMap(): array
    {
        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.equipement) AS eq_id', 'a.beneficiaireNom')
            ->where("a.statut IN ('En cours', 'Prolongé')")
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            // Keep the first active attribution found per equipment
            if (!isset($map[(int) $row['eq_id']])) {
                $map[(int) $row['eq_id']] = $row['beneficiaireNom'];
            }
        }

        return $map;
    }
}
