<?php

namespace App\Repository;

use App\Entity\Personnel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Personnel>
 */
class PersonnelRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Personnel::class);
    }

    /**
     * Recherche avancée avec filtres
     *
     * @return Personnel[]
     */
    public function findByFilters(?string $search = null, ?string $type = null, ?string $service = null, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.attributions', 'a')
            ->addSelect('a')
            ->orderBy('p.nom', 'ASC');

        if ($search !== null && trim($search) !== '') {
            $term = '%' . trim($search) . '%';
            $qb->andWhere('p.nom LIKE :search OR p.matricule LIKE :search OR p.service LIKE :search OR p.email LIKE :search OR p.telephone LIKE :search')
               ->setParameter('search', $term);
        }

        if ($type !== null && trim($type) !== '' && $type !== 'all') {
            $qb->andWhere('p.type = :type')
               ->setParameter('type', trim($type));
        }

        if ($service !== null && trim($service) !== '' && $service !== 'all') {
            $qb->andWhere('p.service = :service')
               ->setParameter('service', trim($service));
        }

        if ($statut !== null && trim($statut) !== '' && $statut !== 'all') {
            $qb->andWhere('p.statut = :statut')
               ->setParameter('statut', trim($statut));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère tous les services distincts existants
     *
     * @return string[]
     */
    public function findDistinctServices(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.service')
            ->where('p.service IS NOT NULL')
            ->andWhere('p.service != :empty')
            ->setParameter('empty', '')
            ->orderBy('p.service', 'ASC')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'service');
    }

    /**
     * Calcule le prochain numéro disponible pour un préfixe donné (ex: PROD -> 59 si max est 58)
     */
    public function getNextMatriculeNumberForPrefix(string $prefix): int
    {
        $pattern = $prefix . '-%';
        $rows = $this->createQueryBuilder('p')
            ->select('p.matricule')
            ->where('p.matricule LIKE :prefix')
            ->setParameter('prefix', $pattern)
            ->getQuery()
            ->getScalarResult();

        $maxNum = 0;
        foreach ($rows as $row) {
            $mat = $row['matricule'] ?? '';
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/', $mat, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        return $maxNum + 1;
    }

    /**
     * Statistiques par type
     *
     * @return array<string, int>
     */
    public function countByType(): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.type, COUNT(p.id) as count')
            ->groupBy('p.type')
            ->getQuery()
            ->getResult();

        $stats = [
            'total' => 0,
            Personnel::TYPE_EMPLOYE => 0,
            Personnel::TYPE_PRESTATAIRE => 0,
            Personnel::TYPE_STAGIAIRE => 0,
            Personnel::TYPE_VISITEUR => 0,
        ];

        foreach ($rows as $row) {
            $t = $row['type'];
            $c = (int) $row['count'];
            $stats[$t] = $c;
            $stats['total'] += $c;
        }

        return $stats;
    }
}

