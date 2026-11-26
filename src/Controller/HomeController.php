<?php

namespace App\Controller;

use App\Entity\Attribution;
use App\Entity\Equipement;
use App\Entity\TicketMaintenance;
use App\Repository\CategorieEquipementRepository;
use App\Repository\LieuRepository;
use App\Repository\PosteAutoriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(
        CategorieEquipementRepository $categorieRepo,
        LieuRepository $lieuRepo,
        PosteAutoriseRepository $posteRepo,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $clientIp = $request->getClientIp() ?? '127.0.0.1';
        $currentPoste = $posteRepo->findOneBy(['adresseIp' => $clientIp]);

        $eqRepo = $em->getRepository(Equipement::class);
        $attRepo = $em->getRepository(Attribution::class);
        $tickRepo = $em->getRepository(TicketMaintenance::class);

        $stats = [
            'equipements_count' => $eqRepo->count([]),
            'en_service' => $eqRepo->count(['statut' => 'En service']),
            'en_stock' => $eqRepo->count(['statut' => 'En stock']),
            'en_panne' => $eqRepo->count(['statut' => 'En panne']),
            'categories_count' => $categorieRepo->count([]),
            'lieux_count' => $lieuRepo->count([]),
            'postes_count' => $posteRepo->count([]),
            'attributions_actives' => $attRepo->count(['statut' => 'En cours']),
            'maintenances_en_cours' => $tickRepo->count(['statut' => ['Nouveau', 'En cours']]),
        ];

        $totalValeur = (float) ($eqRepo->createQueryBuilder('e')
            ->select('SUM(e.prixAchat)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);

        // Répartition par catégorie réelle en base
        $allCategories = $categorieRepo->findBy([], ['nom_cat' => 'ASC']);
        $catDistribution = [];
        foreach ($allCategories as $cat) {
            $catDistribution[] = [
                'label' => $cat->getNomCat(),
                'count' => (int) $eqRepo->count(['categorie' => $cat]),
            ];
        }
        usort($catDistribution, fn($a, $b) => $b['count'] <=> $a['count']);

        // Répartition par lieu réel en base
        $allLieux = $lieuRepo->findBy([], ['nom_lieu' => 'ASC']);
        $lieuxDistribution = [];
        foreach ($allLieux as $lieu) {
            $lieuxDistribution[] = [
                'label' => $lieu->getNomLieu(),
                'count' => (int) $eqRepo->count(['lieu' => $lieu]),
            ];
        }
        usort($lieuxDistribution, fn($a, $b) => $b['count'] <=> $a['count']);

        $threeYearsAgo = new \DateTime('-3 years');
        $fiveYearsAgo = new \DateTime('-5 years');

        $vetusteCount = (int) $eqRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.dateAcquisition IS NOT NULL AND e.dateAcquisition < :threeYearsAgo')
            ->setParameter('threeYearsAgo', $threeYearsAgo)
            ->getQuery()
            ->getSingleScalarResult();

        $recentCount = (int) $eqRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.dateAcquisition IS NOT NULL AND e.dateAcquisition >= :threeYearsAgo')
            ->setParameter('threeYearsAgo', $threeYearsAgo)
            ->getQuery()->getSingleScalarResult();

        $agingCount = (int) $eqRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.dateAcquisition IS NOT NULL AND e.dateAcquisition < :threeYearsAgo AND e.dateAcquisition >= :fiveYearsAgo')
            ->setParameter('threeYearsAgo', $threeYearsAgo)
            ->setParameter('fiveYearsAgo', $fiveYearsAgo)
            ->getQuery()->getSingleScalarResult();

        $obsoleteCount = (int) $eqRepo->createQueryBuilder('e')
            ->select('COUNT(e.id)')
            ->where('e.dateAcquisition IS NOT NULL AND e.dateAcquisition < :fiveYearsAgo')
            ->setParameter('fiveYearsAgo', $fiveYearsAgo)
            ->getQuery()->getSingleScalarResult();

        $ageDistribution = [
            'recent'   => $recentCount,
            'aging'    => $agingCount,
            'obsolete' => $obsoleteCount,
        ];

        // Répartition par Statut Opérationnel
        $statusDistribution = [
            'En service' => (int) $eqRepo->count(['statut' => 'En service']),
            'En stock'   => (int) $eqRepo->count(['statut' => 'En stock']),
            'En panne'   => (int) $eqRepo->count(['statut' => 'En panne']),
            'Prêté'      => (int) $eqRepo->count(['statut' => 'Prêté']),
            'Au rebut'   => (int) $eqRepo->count(['statut' => 'Au rebut']),
        ];

        // Répartition par Système d'Exploitation (OS)
        $osRows = $eqRepo->createQueryBuilder('e')
            ->select('e.systemeExploitation, COUNT(e.id) as count')
            ->where('e.systemeExploitation IS NOT NULL AND e.systemeExploitation != \'\'')
            ->groupBy('e.systemeExploitation')
            ->orderBy('count', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getResult();
        $osDistribution = [];
        foreach ($osRows as $row) {
            $osDistribution[] = [
                'label' => $row['systemeExploitation'],
                'count' => (int) $row['count'],
            ];
        }

        $totalEq = $stats['equipements_count'];
        $enServicePct = $totalEq > 0 ? (int) round(($stats['en_service'] / $totalEq) * 100) : 0;
        $enStockPct = $totalEq > 0 ? (int) round(($stats['en_stock'] / $totalEq) * 100) : 0;
        $vetustePct = $totalEq > 0 ? (int) round(($vetusteCount / $totalEq) * 100) : 0;

        $recentEquipements = $eqRepo->findBy([], ['dateAjout' => 'DESC'], 5);
        $recentTickets = $tickRepo->findBy(['statut' => ['Nouveau', 'En cours']], ['dateCreation' => 'DESC'], 4);
        $recentAttributions = $attRepo->findBy(['statut' => 'En cours'], ['dateDebut' => 'DESC'], 5);

        return $this->render('home/index.html.twig', [
            'stats'               => $stats,
            'client_ip'           => $clientIp,
            'current_poste'       => $currentPoste,
            'total_valeur'        => $totalValeur,
            'cat_distribution'    => $catDistribution,
            'lieux_distribution'  => $lieuxDistribution,
            'status_distribution' => $statusDistribution,
            'os_distribution'     => $osDistribution,
            'age_distribution'    => $ageDistribution,
            'vetuste_count'       => $vetusteCount,
            'en_service_pct'      => $enServicePct,
            'en_stock_pct'        => $enStockPct,
            'vetuste_pct'         => $vetustePct,
            'recent_equipements'  => $recentEquipements,
            'recent_tickets'      => $recentTickets,
            'recent_attributions' => $recentAttributions,
        ]);
    }
}
