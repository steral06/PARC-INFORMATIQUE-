<?php

namespace App\Service;

use App\Entity\Attribution;
use App\Entity\Composant;
use App\Entity\Equipement;
use App\Entity\TicketMaintenance;
use Doctrine\ORM\EntityManagerInterface;

class AlertingService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {
    }

    /**
     * Analyse en temps réel l'ensemble du parc et renvoie la synthèse des alertes proactives.
     *
     * @return array{
     *     total_alerts: int,
     *     critical_count: int,
     *     warning_count: int,
     *     alerts: array<int, array{
     *         type: string,
     *         level: 'danger'|'warning'|'info',
     *         icon: string,
     *         title: string,
     *         description: string,
     *         count: int,
     *         link: string,
     *         items: array<int, array{id: int|string, label: string, detail: string}>
     *     }>
     * }
     */
    public function getActiveAlerts(): array
    {
        $alerts = [];
        $criticalCount = 0;
        $warningCount = 0;

        // 1. Équipements Obsolètes (> 5 ans) en service
        $eqRepo = $this->em->getRepository(Equipement::class);
        $fiveYearsAgo = new \DateTime('-5 years');
        $obsoleteEquipements = $eqRepo->createQueryBuilder('e')
            ->where('e.statut = :statut')
            ->andWhere('e.dateAcquisition IS NOT NULL AND e.dateAcquisition < :fiveYearsAgo')
            ->setParameter('statut', 'En service')
            ->setParameter('fiveYearsAgo', $fiveYearsAgo)
            ->orderBy('e.dateAcquisition', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $totalObsoleteCount = (int) $eqRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.statut = :statut')
            ->andWhere('e.dateAcquisition IS NOT NULL AND e.dateAcquisition < :fiveYearsAgo')
            ->setParameter('statut', 'En service')
            ->setParameter('fiveYearsAgo', $fiveYearsAgo)
            ->getQuery()
            ->getSingleScalarResult();

        if ($totalObsoleteCount > 0) {
            $criticalCount += $totalObsoleteCount;
            $items = [];
            foreach ($obsoleteEquipements as $eq) {
                $items[] = [
                    'id'     => $eq->getId(),
                    'label'  => $eq->getNom() . ' (' . ($eq->getModele() ?: 'Sans modèle') . ')',
                    'detail' => sprintf('Âge : %s ans (Acquis le %s)', $eq->getAgeAnnees() ?? 5, $eq->getDateAcquisition()?->format('d/m/Y') ?: 'N/C'),
                ];
            }
            $alerts[] = [
                'type'        => 'obsolescence',
                'level'       => 'danger',
                'icon'        => 'bi-exclamation-octagon-fill',
                'title'       => 'Obsolescence Critique (> 5 ans)',
                'description' => sprintf('%d équipement(s) en service dépassent la durée de vie préconisée GTAC2A.', $totalObsoleteCount),
                'count'       => $totalObsoleteCount,
                'link'        => '/equipements?statut=En+service',
                'items'       => $items,
            ];
        }

        // 2. Garanties expirant sous 30 jours
        $now = new \DateTime();
        $in30Days = (new \DateTime())->modify('+30 days');
        $expiringEquipements = $eqRepo->createQueryBuilder('e')
            ->where('e.statut != :rebut')
            ->andWhere('e.finGarantie IS NOT NULL')
            ->andWhere('e.finGarantie >= :now AND e.finGarantie <= :in30Days')
            ->setParameter('rebut', 'Au rebut')
            ->setParameter('now', $now)
            ->setParameter('in30Days', $in30Days)
            ->orderBy('e.finGarantie', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $totalExpiringCount = count($expiringEquipements);
        if ($totalExpiringCount > 0) {
            $warningCount += $totalExpiringCount;
            $items = [];
            foreach ($expiringEquipements as $eq) {
                $items[] = [
                    'id'     => $eq->getId(),
                    'label'  => $eq->getNom(),
                    'detail' => 'Garantie expire le ' . $eq->getFinGarantie()?->format('d/m/Y'),
                ];
            }
            $alerts[] = [
                'type'        => 'garantie',
                'level'       => 'warning',
                'icon'        => 'bi-shield-exclamation',
                'title'       => 'Garanties Expirant Bientôt (sous 30j)',
                'description' => sprintf('%d équipement(s) vont perdre leur couverture constructeur sous un mois.', $totalExpiringCount),
                'count'       => $totalExpiringCount,
                'link'        => '/rapports?scope=equipements',
                'items'       => $items,
            ];
        }

        // 3. Composants / Pièces en stock critique (<= 2 unités)
        $compRepo = $this->em->getRepository(Composant::class);
        $lowStockComps = $compRepo->createQueryBuilder('c')
            ->where('c.quantite <= 2 AND c.quantite >= 0')
            ->andWhere('c.statut = :enStock')
            ->setParameter('enStock', 'En stock')
            ->orderBy('c.quantite', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $totalLowStock = count($lowStockComps);
        if ($totalLowStock > 0) {
            $warningCount += $totalLowStock;
            $items = [];
            foreach ($lowStockComps as $c) {
                $items[] = [
                    'id'     => $c->getId(),
                    'label'  => $c->getNom() . ' (' . ($c->getType() ?: 'Composant') . ')',
                    'detail' => sprintf('Reste : %d unité(s) en réserve', $c->getQuantite()),
                ];
            }
            $alerts[] = [
                'type'        => 'stock_composant',
                'level'       => 'warning',
                'icon'        => 'bi-cpu-fill',
                'title'       => 'Rupture Proche : Pièces en Stock Critique',
                'description' => sprintf('%d référence(s) de pièces détachées ont un stock inférieur ou égal à 2.', $totalLowStock),
                'count'       => $totalLowStock,
                'link'        => '/composants?statut=En+stock',
                'items'       => $items,
            ];
        }

        // 4. Dotations / Prêts dont la restitution est en retard
        $attRepo = $this->em->getRepository(Attribution::class);
        $overdueAttributions = $attRepo->createQueryBuilder('a')
            ->where('a.statut IN (:statuts)')
            ->andWhere('a.dateFinPrevue IS NOT NULL AND a.dateFinPrevue < :today')
            ->setParameter('statuts', ['En cours', 'Prolongé'])
            ->setParameter('today', $now->format('Y-m-d'))
            ->orderBy('a.dateFinPrevue', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $totalOverdueCount = count($overdueAttributions);
        if ($totalOverdueCount > 0) {
            $warningCount += $totalOverdueCount;
            $items = [];
            foreach ($overdueAttributions as $att) {
                $items[] = [
                    'id'     => $att->getId(),
                    'label'  => ($att->getEquipement()?->getNom() ?: 'Matériel') . ' -> ' . $att->getBeneficiaireNom(),
                    'detail' => 'Retour attendu le ' . $att->getDateFinPrevue()?->format('d/m/Y'),
                ];
            }
            $alerts[] = [
                'type'        => 'restitution_retard',
                'level'       => 'danger',
                'icon'        => 'bi-clock-history',
                'title'       => 'Prêts de Matériel Non Restitués (En Retard)',
                'description' => sprintf('%d prêt(s) ont dépassé la date limite convenue.', $totalOverdueCount),
                'count'       => $totalOverdueCount,
                'link'        => '/attributions?statut=En+cours',
                'items'       => $items,
            ];
        }

        // 5. Incidents / Pannes critiques non résolus
        $tickRepo = $this->em->getRepository(TicketMaintenance::class);
        $urgentTickets = $tickRepo->createQueryBuilder('t')
            ->where('t.statut IN (:statuts)')
            ->andWhere('t.priorite IN (:priorites)')
            ->setParameter('statuts', ['Nouveau', 'En cours'])
            ->setParameter('priorites', ['Haute', 'Critique'])
            ->orderBy('t.dateCreation', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        $totalUrgentTickets = count($urgentTickets);
        if ($totalUrgentTickets > 0) {
            $criticalCount += $totalUrgentTickets;
            $items = [];
            foreach ($urgentTickets as $t) {
                $items[] = [
                    'id'     => $t->getId(),
                    'label'  => '#' . $t->getId() . ' - ' . $t->getTitre(),
                    'detail' => sprintf('Priorité %s &bull; Matériel : %s', $t->getPriorite(), $t->getEquipement()?->getNom() ?: 'Retiré'),
                ];
            }
            $alerts[] = [
                'type'        => 'panne_urgente',
                'level'       => 'danger',
                'icon'        => 'bi-tools',
                'title'       => 'Pannes Critiques en Attente SAV',
                'description' => sprintf('%d intervention(s) haute priorité nécessitent une action urgente.', $totalUrgentTickets),
                'count'       => $totalUrgentTickets,
                'link'        => '/maintenances?statut=all',
                'items'       => $items,
            ];
        }

        return [
            'total_alerts'   => count($alerts),
            'critical_count' => $criticalCount,
            'warning_count'  => $warningCount,
            'alerts'         => $alerts,
        ];
    }
}

