<?php

namespace App\Controller;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/integrite', name: 'app_integrite_')]
class IntegriteController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        Request $request,
        AuditLogRepository $auditLogRepository,
    ): Response {
        $action = trim((string) $request->query->get('action', ''));
        $entityType = trim((string) $request->query->get('entity_type', ''));
        $userIdentifier = trim((string) $request->query->get('user', ''));
        $dateFrom = $request->query->get('date_from') ? new \DateTimeImmutable($request->query->get('date_from')) : null;
        $dateTo = $request->query->get('date_to') ? new \DateTimeImmutable($request->query->get('date_to')) : null;

        $logs = $auditLogRepository->findByFilters(
            $action ?: null,
            $entityType ?: null,
            $userIdentifier ?: null,
            $dateFrom,
            $dateTo,
            500
        );

        $actionStats = $auditLogRepository->getActionStats();
        $entityTypeStats = $auditLogRepository->getEntityTypeStats();
        $allActions = array_unique(array_column($logs, 'action'));
        $allEntityTypes = array_unique(array_column(array_filter($logs, fn($l) => $l->getEntityType()), 'entityType'));

        return $this->render('integrite/index.html.twig', [
            'logs' => $logs,
            'actionStats' => $actionStats,
            'entityTypeStats' => $entityTypeStats,
            'allActions' => $allActions,
            'allEntityTypes' => $allEntityTypes,
            'current_filters' => [
                'action' => $action,
                'entity_type' => $entityType,
                'user' => $userIdentifier,
                'date_from' => $dateFrom?->format('Y-m-d'),
                'date_to' => $dateTo?->format('Y-m-d'),
            ],
        ]);
    }

    #[Route('/purger', name: 'purge', methods: ['POST'])]
    public function purge(
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('integrite_purge', $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_integrite_index');
        }

        $days = (int) $request->request->get('days', 90);
        if ($days < 1) {
            $days = 1;
        }

        $cutoff = new \DateTimeImmutable("-{$days} days");

        $deletedCount = $em->createQueryBuilder()
            ->delete(AuditLog::class, 'a')
            ->where('a.createdAt < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();

        $this->addFlash('success', "Purge effectuée : {$deletedCount} entrées de journal supprimées (plus de {$days} jours).");

        return $this->redirectToRoute('app_integrite_index');
    }
}