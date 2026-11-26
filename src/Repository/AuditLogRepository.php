<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findLatest(int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findByFiltersPaginated(
        ?string $action = null,
        ?string $level = null,
        ?string $status = null,
        ?string $username = null,
        ?string $entityType = null,
        ?\DateTimeInterface $dateFrom = null,
        ?\DateTimeInterface $dateTo = null,
        ?string $search = null,
        int $page = 1,
        int $limit = 25
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if ($search) {
            $qb->andWhere('a.description LIKE :search OR a.username LIKE :search OR a.ipAddress LIKE :search OR a.entityId LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($action) {
            $qb->andWhere('a.action = :action')
               ->setParameter('action', $action);
        }

        if ($level) {
            $qb->andWhere('a.level = :level')
               ->setParameter('level', $level);
        }

        if ($status) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }

        if ($username) {
            $qb->andWhere('a.username = :username')
               ->setParameter('username', $username);
        }

        if ($entityType) {
            $qb->andWhere('a.entityType = :entityType')
               ->setParameter('entityType', $entityType);
        }

        if ($dateFrom) {
            $qb->andWhere('a.createdAt >= :dateFrom')
               ->setParameter('dateFrom', $dateFrom);
        }

        if ($dateTo) {
            // Include the entire end day
            $dateToEnd = clone $dateTo;
            $dateToEnd = $dateToEnd->modify('+1 day');
            $qb->andWhere('a.createdAt < :dateTo')
               ->setParameter('dateTo', $dateToEnd);
        }

        $query = $qb->getQuery();
        $query->setFirstResult(($page - 1) * $limit)
              ->setMaxResults($limit);

        $paginator = new Paginator($query, fetchJoinCollection: true);

        return [
            'items' => iterator_to_array($paginator),
            'total' => count($paginator),
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) ceil(count($paginator) / $limit),
        ];
    }

    public function getDistinctUsernames(): array
    {
        return $this->createQueryBuilder('a')
            ->select('DISTINCT a.username')
            ->where('a.username IS NOT NULL')
            ->orderBy('a.username', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function getDistinctActions(): array
    {
        return $this->createQueryBuilder('a')
            ->select('DISTINCT a.action')
            ->orderBy('a.action', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function getDistinctLevels(): array
    {
        return $this->createQueryBuilder('a')
            ->select('DISTINCT a.level')
            ->orderBy('a.level', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    public function getDistinctEntityTypes(): array
    {
        return $this->createQueryBuilder('a')
            ->select('DISTINCT a.entityType')
            ->where('a.entityType IS NOT NULL')
            ->orderBy('a.entityType', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }

    /**
     * Statistiques globales et récentes pour le tableau de bord d'audit
     *
     * @return array{total: int, today: int, warnings: int, errors: int, success_rate: int}
     */
    public function getStats(): array
    {
        $todayStart = (new \DateTimeImmutable('today'))->format('Y-m-d H:i:s');

        $total = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $today = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.createdAt >= :today')
            ->setParameter('today', new \DateTimeImmutable($todayStart))
            ->getQuery()
            ->getSingleScalarResult();

        $warnings = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.level = :level')
            ->setParameter('level', 'WARNING')
            ->getQuery()
            ->getSingleScalarResult();

        $errors = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.level = :level OR a.status = :status')
            ->setParameter('level', 'ERROR')
            ->setParameter('status', 'ECHEC')
            ->getQuery()
            ->getSingleScalarResult();

        $success = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :status')
            ->setParameter('status', 'SUCCES')
            ->getQuery()
            ->getSingleScalarResult();

        $successRate = $total > 0 ? (int) round(($success / $total) * 100) : 100;

        return [
            'total' => $total,
            'today' => $today,
            'warnings' => $warnings,
            'errors' => $errors,
            'success_rate' => $successRate,
        ];
    }
}