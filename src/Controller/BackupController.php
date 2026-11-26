<?php

namespace App\Controller;

use App\Service\AuditLogger;
use App\Service\BackupManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/admin/sauvegardes', name: 'app_backup_')]
class BackupController extends AbstractController
{
    public function __construct(
        private readonly BackupManager $backupManager,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    #[Route('', name: 'index', methods: ['GET'])]
    public function index(): Response
    {
        $backups = $this->backupManager->listBackups();
        $dbSize = $this->backupManager->getDatabaseSize();

        return $this->render('backup/index.html.twig', [
            'backups'  => $backups,
            'db_size'  => $dbSize,
            'db_path'  => basename($this->backupManager->getDatabasePath()),
        ]);
    }

    #[Route('/create', name: 'create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('backup_create', $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_backup_index');
        }

        $result = $this->backupManager->createBackup('admin_manual');

        $currentUser = $this->getUser()?->getUserIdentifier() ?? 'superadmin';
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        if ($result['success']) {
            $this->auditLogger->log(
                'BACKUP_CREATE',
                'INFO',
                'SUCCES',
                $currentUser,
                $clientIp,
                'Backup',
                $result['filename'],
                sprintf('Création manuelle de sauvegarde réussie : %s (%s)', $result['filename'], $result['size']),
                $result
            );
            $this->addFlash('success', sprintf('Sauvegarde générée avec succès : %s (%s).', $result['filename'], $result['size']));
        } else {
            $this->auditLogger->log(
                'BACKUP_CREATE',
                'ERROR',
                'ECHEC',
                $currentUser,
                $clientIp,
                'Backup',
                null,
                'Échec de la création manuelle de sauvegarde : ' . ($result['error'] ?? '')
            );
            $this->addFlash('danger', 'Échec de la sauvegarde : ' . ($result['error'] ?? 'Erreur inconnue'));
        }

        return $this->redirectToRoute('app_backup_index');
    }

    #[Route('/download/{filename}', name: 'download', methods: ['GET'], requirements: ['filename' => '.+'])]
    public function download(string $filename, Request $request): Response
    {
        $path = $this->backupManager->getBackupFilePath($filename);
        if (!$path) {
            $this->addFlash('danger', 'Fichier de sauvegarde introuvable.');
            return $this->redirectToRoute('app_backup_index');
        }

        $currentUser = $this->getUser()?->getUserIdentifier() ?? 'superadmin';
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        $this->auditLogger->log(
            'BACKUP_DOWNLOAD',
            'INFO',
            'SUCCES',
            $currentUser,
            $clientIp,
            'Backup',
            $filename,
            sprintf('Téléchargement de la sauvegarde : %s', $filename)
        );

        $response = new BinaryFileResponse($path);
        $response->headers->set('Content-Type', 'application/octet-stream');
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, $filename);
        return $response;
    }

    #[Route('/restore/{filename}', name: 'restore', methods: ['POST'])]
    public function restore(string $filename, Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('backup_restore_' . $filename, $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_backup_index');
        }

        $currentUser = $this->getUser()?->getUserIdentifier() ?? 'superadmin';
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        $result = $this->backupManager->restoreBackup($filename);

        if ($result['success']) {
            $this->auditLogger->log(
                'BACKUP_RESTORE',
                'CRITICAL',
                'SUCCES',
                $currentUser,
                $clientIp,
                'Backup',
                $filename,
                'Restauration de la base de données exécutée avec succès depuis : ' . $filename
            );
            $this->addFlash('success', $result['message']);
        } else {
            $this->auditLogger->log(
                'BACKUP_RESTORE',
                'CRITICAL',
                'ECHEC',
                $currentUser,
                $clientIp,
                'Backup',
                $filename,
                'Échec de la restauration de la base : ' . $result['message']
            );
            $this->addFlash('danger', 'Erreur lors de la restauration : ' . $result['message']);
        }

        return $this->redirectToRoute('app_backup_index');
    }

    #[Route('/delete/{filename}', name: 'delete', methods: ['POST'])]
    public function delete(string $filename, Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('backup_delete_' . $filename, $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_backup_index');
        }

        $currentUser = $this->getUser()?->getUserIdentifier() ?? 'superadmin';
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        if ($this->backupManager->deleteBackup($filename)) {
            $this->auditLogger->log(
                'BACKUP_DELETE',
                'WARNING',
                'SUCCES',
                $currentUser,
                $clientIp,
                'Backup',
                $filename,
                sprintf('Suppression de l\'archive de sauvegarde : %s', $filename)
            );
            $this->addFlash('success', sprintf('Sauvegarde "%s" supprimée avec succès.', $filename));
        } else {
            $this->addFlash('danger', 'Impossible de supprimer ce fichier.');
        }

        return $this->redirectToRoute('app_backup_index');
    }
}

