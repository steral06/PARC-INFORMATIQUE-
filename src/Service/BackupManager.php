<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class BackupManager
{
    private string $backupDir;
    private string $dbPath;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        private readonly LoggerInterface $logger,
    ) {
        $this->backupDir = $this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'backups';
        $this->dbPath = $this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'data_' . $this->environment . '.db';

        if (!is_dir($this->backupDir)) {
            @mkdir($this->backupDir, 0775, true);
        }
    }

    /**
     * Effectue une sauvegarde atomique et sécurisée de la base SQLite.
     *
     * @return array{success: bool, filename?: string, size?: string, error?: string}
     */
    public function createBackup(string $prefix = 'gparc_backup'): array
    {
        if (!file_exists($this->dbPath)) {
            return ['success' => false, 'error' => 'Fichier de base de données introuvable : ' . $this->dbPath];
        }

        $dateStr = (new \DateTimeImmutable())->format('Y-m-d_H-i-s');
        $filename = sprintf('%s_%s_%s.db', $prefix, $this->environment, $dateStr);
        $destPath = $this->backupDir . DIRECTORY_SEPARATOR . $filename;

        try {
            // Utiliser l'API de sauvegarde SQLite native si disponible, sinon copie sécurisée
            if (class_exists(\SQLite3::class)) {
                $src = new \SQLite3($this->dbPath, SQLITE3_OPEN_READONLY);
                $dest = new \SQLite3($destPath, SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);
                if (method_exists($src, 'backup')) {
                    $src->backup($dest);
                } else {
                    $src->close();
                    copy($this->dbPath, $destPath);
                }
                $src->close();
                $dest->close();
            } else {
                if (!copy($this->dbPath, $destPath)) {
                    throw new \RuntimeException('Échec de la copie du fichier SQLite.');
                }
            }

            $sizeBytes = filesize($destPath) ?: 0;
            return [
                'success'  => true,
                'filename' => $filename,
                'size'     => $this->formatBytes($sizeBytes),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de la sauvegarde SQLite : ' . $e->getMessage());
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Liste toutes les sauvegardes disponibles dans le dossier var/backups.
     *
     * @return array<int, array{filename: string, size_bytes: int, size_formatted: string, date: string, timestamp: int}>
     */
    public function listBackups(): array
    {
        if (!is_dir($this->backupDir)) {
            return [];
        }

        $files = scandir($this->backupDir, SCANDIR_SORT_DESCENDING);
        if ($files === false) {
            return [];
        }

        $backups = [];
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with($file, '.db')) {
                continue;
            }

            $fullPath = $this->backupDir . DIRECTORY_SEPARATOR . $file;
            $stat = stat($fullPath);
            $size = $stat ? ($stat['size'] ?? 0) : 0;
            $mtime = $stat ? ($stat['mtime'] ?? time()) : time();

            $backups[] = [
                'filename'       => $file,
                'size_bytes'     => $size,
                'size_formatted' => $this->formatBytes($size),
                'date'           => (new \DateTimeImmutable('@' . $mtime))->setTimezone(new \DateTimeZone('Africa/Lome'))->format('d/m/Y H:i:s'),
                'timestamp'      => $mtime,
            ];
        }

        usort($backups, fn($a, $b) => $b['timestamp'] <=> $a['timestamp']);
        return $backups;
    }

    /**
     * Restaure une sauvegarde existante.
     * Une sauvegarde de sécurité de la base actuelle est automatiquement créée au préalable.
     *
     * @return array{success: bool, message: string}
     */
    public function restoreBackup(string $filename): array
    {
        $sanitized = basename($filename);
        $sourcePath = $this->backupDir . DIRECTORY_SEPARATOR . $sanitized;

        if (!file_exists($sourcePath)) {
            return ['success' => false, 'message' => 'Le fichier de sauvegarde spécifié n\'existe pas.'];
        }

        // 1. Sauvegarde automatique préalable de l'état actuel (Filet de sécurité)
        $safety = $this->createBackup('pre_restore_safety');
        if (!$safety['success']) {
            return ['success' => false, 'message' => 'Annulé : impossible de créer la sauvegarde de sécurité préalable.'];
        }

        // 2. Écrasement propre de la base actuelle
        try {
            if (!copy($sourcePath, $this->dbPath)) {
                throw new \RuntimeException('Impossible de copier la sauvegarde vers la base active.');
            }
            return [
                'success' => true,
                'message' => sprintf('Restauration réussie depuis "%s". Un instantané de secours a été créé (%s).', $sanitized, $safety['filename'] ?? ''),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Erreur lors de la restauration de la sauvegarde : ' . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function deleteBackup(string $filename): bool
    {
        $sanitized = basename($filename);
        $filePath = $this->backupDir . DIRECTORY_SEPARATOR . $sanitized;
        if (file_exists($filePath)) {
            return @unlink($filePath);
        }
        return false;
    }

    public function getBackupFilePath(string $filename): ?string
    {
        $sanitized = basename($filename);
        $filePath = $this->backupDir . DIRECTORY_SEPARATOR . $sanitized;
        return file_exists($filePath) ? $filePath : null;
    }

    public function getDatabaseSize(): string
    {
        if (file_exists($this->dbPath)) {
            $bytes = filesize($this->dbPath) ?: 0;
            return $this->formatBytes($bytes);
        }
        return '0 Ko';
    }

    public function getDatabasePath(): string
    {
        return $this->dbPath;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2, ',', ' ') . ' Mo';
        }
        if ($bytes >= 1024) {
            return number_format($bytes / 1024, 1, ',', ' ') . ' Ko';
        }
        return $bytes . ' octets';
    }
}

