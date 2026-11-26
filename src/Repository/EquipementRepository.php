<?php

namespace App\Repository;

use App\Entity\Attribution;
use App\Entity\Equipement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Equipement>
 */
class EquipementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Equipement::class);
    }

    /**
     * Recherche multicritère optimisée pour la liste du parc
     *
     * @return Equipement[]
     */
    public function findByFilters(?string $search = null, ?string $catId = null, ?string $statut = null, ?int $lieuId = null): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.categorie', 'c')
            ->leftJoin('e.lieu', 'l')
            ->addSelect('c', 'l')
            ->orderBy('e.dateAjout', 'DESC');

        if ($search !== null && $search !== '') {
            $qb->andWhere(
                'e.nom LIKE :search 
                OR e.numeroSerie LIKE :search 
                OR e.marque LIKE :search 
                OR e.modele LIKE :search 
                OR e.tagInventaire LIKE :search 
                OR e.adresseIp LIKE :search
                OR EXISTS (
                    SELECT 1 FROM ' . Attribution::class . ' a
                    WHERE a.equipement = e
                    AND (a.beneficiaireNom LIKE :search OR a.beneficiaireService LIKE :search)
                )'
            )->setParameter('search', '%' . $search . '%');
        }

        if (!empty($catId)) {
            $qb->andWhere('e.categorie = :catId')
               ->setParameter('catId', $catId);
        }

        if (!empty($statut)) {
            $qb->andWhere('e.statut = :statut')
               ->setParameter('statut', $statut);
        }

        if (!empty($lieuId)) {
            $qb->andWhere('e.lieu = :lieuId')
               ->setParameter('lieuId', $lieuId);
        }

        return $qb->getQuery()->getResult();
    }
}
