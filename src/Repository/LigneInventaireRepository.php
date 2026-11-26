<?php

namespace App\Repository;

use App\Entity\CampagneInventaire;
use App\Entity\LigneInventaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LigneInventaire>
 */
class LigneInventaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LigneInventaire::class);
    }

    /**
     * @return LigneInventaire[]
     */
    public function findByCampagneAndFilters(
        CampagneInventaire $campagne,
        ?string $search = null,
        ?string $statut = null,
        ?int $lieuId = null,
    ): array {
        $qb = $this->createQueryBuilder('l')
            ->join('l.equipement', 'e')
            ->leftJoin('e.lieu', 'el')
            ->leftJoin('l.lieuConstate', 'lc')
            ->where('l.campagne = :campagne')
            ->setParameter('campagne', $campagne);

        if ($search) {
            $qb->andWhere('e.nom LIKE :search OR e.numeroSerie LIKE :search OR e.tagInventaire LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($statut && $statut !== 'all') {
            $qb->andWhere('l.statutConstate = :statut')
                ->setParameter('statut', $statut);
        }

        if ($lieuId) {
            $qb->andWhere('e.lieu = :lieuId')
                ->setParameter('lieuId', $lieuId);
        }

        return $qb->orderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne uniquement les écarts constatés lors de la campagne.
     *
     * @return LigneInventaire[]
     */
    public function findEcarts(CampagneInventaire $campagne): array
    {
        return $this->createQueryBuilder('l')
            ->join('l.equipement', 'e')
            ->where('l.campagne = :campagne')
            ->andWhere('l.statutConstate IN (:ecarts)')
            ->setParameter('campagne', $campagne)
            ->setParameter('ecarts', [
                LigneInventaire::STATUT_DEPLACE,
                LigneInventaire::STATUT_MANQUANT,
                LigneInventaire::STATUT_HORS_SERVICE,
            ])
            ->orderBy('l.statutConstate', 'ASC')
            ->addOrderBy('e.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

