<?php

namespace App\Controller;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/admin/audit', name: 'app_audit_')]
class AuditController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, AuditLogRepository $auditLogRepository): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;

        $search = trim((string) $request->query->get('q', ''));
        $action = trim((string) $request->query->get('action', ''));
        $level = trim((string) $request->query->get('level', ''));
        $status = trim((string) $request->query->get('status', ''));
        $username = trim((string) $request->query->get('username', ''));
        $entityType = trim((string) $request->query->get('entity_type', ''));
        $dateFrom = $request->query->get('date_from') ? new \DateTimeImmutable($request->query->get('date_from')) : null;
        $dateTo = $request->query->get('date_to') ? new \DateTimeImmutable($request->query->get('date_to')) : null;

        $result = $auditLogRepository->findByFiltersPaginated(
            $action ?: null,
            $level ?: null,
            $status ?: null,
            $username ?: null,
            $entityType ?: null,
            $dateFrom,
            $dateTo,
            $search ?: null,
            $page,
            $limit
        );

        $usernames = $auditLogRepository->getDistinctUsernames();
        $actions = $auditLogRepository->getDistinctActions();
        $levels = $auditLogRepository->getDistinctLevels();
        $entityTypes = $auditLogRepository->getDistinctEntityTypes();
        $stats = $auditLogRepository->getStats();

        // Vérifier si la purge initiale de déploiement a déjà été effectuée
        $isPurged = false;
        try {
            $conn = $auditLogRepository->getEntityManager()->getConnection();
            $val = $conn->fetchOne("SELECT value FROM app_setting WHERE id = 'audit_deployment_purged'");
            $isPurged = ($val === '1');
        } catch (\Throwable) {
            $isPurged = false;
        }

        return $this->render('audit/index.html.twig', [
            'logs' => $result['items'],
            'total' => $result['total'],
            'page' => $result['page'],
            'pages' => $result['pages'],
            'limit' => $limit,
            'usernames' => $usernames,
            'actions' => $actions,
            'levels' => $levels,
            'entityTypes' => $entityTypes,
            'stats' => $stats,
            'is_purged' => $isPurged,
            'filters' => [
                'q' => $search,
                'action' => $action,
                'level' => $level,
                'status' => $status,
                'username' => $username,
                'entity_type' => $entityType,
                'date_from' => $dateFrom?->format('Y-m-d'),
                'date_to' => $dateTo?->format('Y-m-d'),
            ],
        ]);
    }

    /**
     * Purge temporaire à usage unique pour initialiser un journal d'audit vierge le jour du déploiement.
     */
    #[Route('/purger', name: 'purger', methods: ['POST'])]
    public function purger(Request $request, EntityManagerInterface $em): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('audit_purge_initiale', $token)) {
            $this->addFlash('danger', 'Jeton CSRF de sécurité invalide.');
            return $this->redirectToRoute('app_audit_index');
        }

        $conn = $em->getConnection();

        try {
            $conn->executeStatement('DELETE FROM audit_log');
            try {
                $conn->executeStatement("DELETE FROM sqlite_sequence WHERE name = 'audit_log'");
            } catch (\Throwable) {}

            // Enregistrer l'état "purgé pour déploiement" afin que le bouton ne réapparaisse plus jamais
            $conn->executeStatement("CREATE TABLE IF NOT EXISTS app_setting (id VARCHAR(50) PRIMARY KEY, value TEXT, updated_at DATETIME)");
            $conn->executeStatement("INSERT OR REPLACE INTO app_setting (id, value, updated_at) VALUES ('audit_deployment_purged', '1', datetime('now'))");

            $this->addFlash('success', 'Le journal d\'audit a été purgé avec succès. Les traces de développement et tests ont été réinitialisées pour le déploiement.');
        } catch (\Throwable $e) {
            $this->addFlash('danger', 'Erreur lors de la purge de l\'audit : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_audit_index');
    }
}