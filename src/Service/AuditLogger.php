<?php

namespace App\Service;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;

class AuditLogger
{
    private const MAX_RETRIES = 1;

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function log(
        string $action,
        string $level,
        string $status,
        ?string $username,
        ?string $ipAddress,
        ?string $entityType,
        ?string $entityId,
        string $description,
        ?array $details = null,
    ): void {
        $previousHash = $this->getLastHash();
        $hash = $this->computeHash($previousHash, $action, $username ?? '', $description, $details);

        $sql = 'INSERT INTO audit_log 
            (action, level, status, entity_type, entity_id, username, description, details, ip_address, created_at, previous_hash, hash)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

        $params = [
            $action,
            $level,
            $status,
            $entityType,
            $entityId,
            $username,
            $description,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $ipAddress,
            (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            $previousHash,
            $hash,
        ];

        try {
            $this->connection->executeStatement($sql, $params);
        } catch (\Throwable $e) {
            $this->logger->error('AuditLog insert failed: ' . $e->getMessage(), [
                'exception' => $e,
                'action' => $action,
                'username' => $username,
            ]);
        }
    }

    public function logConnexion(string $username, string $ipAddress, bool $success): void
    {
        $this->log(
            action: 'CONNEXION',
            level: $success ? 'INFO' : 'WARNING',
            status: $success ? 'SUCCES' : 'ECHEC',
            username: $username,
            ipAddress: $ipAddress,
            entityType: null,
            entityId: null,
            description: sprintf('L\'utilisateur %s s\'est connecté depuis l\'adresse IP %s', $username, $ipAddress),
            details: null,
        );
    }

    public function logDeconnexion(string $username, string $ipAddress): void
    {
        $this->log(
            action: 'DECONNEXION',
            level: 'INFO',
            status: 'SUCCES',
            username: $username,
            ipAddress: $ipAddress,
            entityType: null,
            entityId: null,
            description: sprintf('L\'utilisateur %s s\'est déconnecté depuis l\'adresse IP %s', $username, $ipAddress),
            details: null,
        );
    }

    public function logEchecConnexion(string $username, string $ipAddress, string $reason = ''): void
    {
        $this->log(
            action: 'ECHEC_CONNEXION',
            level: 'WARNING',
            status: 'ECHEC',
            username: $username,
            ipAddress: $ipAddress,
            entityType: null,
            entityId: null,
            description: sprintf('Échec de connexion pour l\'utilisateur %s depuis l\'adresse IP %s%s', $username, $ipAddress, $reason ? ' : ' . $reason : ''),
            details: null,
        );
    }

    public function logAccesRefuse(string $ipAddress, string $username = 'Anonyme'): void
    {
        $this->log(
            action: 'ACCES_REFUSE',
            level: 'WARNING',
            status: 'ECHEC',
            username: $username,
            ipAddress: $ipAddress,
            entityType: null,
            entityId: null,
            description: sprintf('Accès refusé pour l\'IP %s (utilisateur : %s)', $ipAddress, $username),
            details: null,
        );
    }

    public function logCreation(string $entityType, string $entityId, string $entityLabel, string $username, string $ipAddress, ?array $details = null): void
    {
        $this->log(
            action: 'CREATION',
            level: 'INFO',
            status: 'SUCCES',
            username: $username,
            ipAddress: $ipAddress,
            entityType: $entityType,
            entityId: $entityId,
            description: sprintf('L\'utilisateur %s a créé %s "%s"', $username, $entityType, $entityLabel),
            details: $details,
        );
    }

    public function logModification(string $entityType, string $entityId, string $entityLabel, string $username, string $ipAddress, array $changes): void
    {
        $this->log(
            action: 'MODIFICATION',
            level: 'INFO',
            status: 'SUCCES',
            username: $username,
            ipAddress: $ipAddress,
            entityType: $entityType,
            entityId: $entityId,
            description: sprintf('L\'utilisateur %s a modifié %s "%s"', $username, $entityType, $entityLabel),
            details: $changes,
        );
    }

    public function logSuppression(string $entityType, string $entityId, string $entityLabel, string $username, string $ipAddress, ?array $details = null): void
    {
        $this->log(
            action: 'SUPPRESSION',
            level: 'WARNING',
            status: 'SUCCES',
            username: $username,
            ipAddress: $ipAddress,
            entityType: $entityType,
            entityId: $entityId,
            description: sprintf('L\'utilisateur %s a supprimé %s "%s"', $username, $entityType, $entityLabel),
            details: $details,
        );
    }

    public function logConfiguration(string $entityType, string $entityId, string $entityLabel, string $username, string $ipAddress, string $operation, ?array $details = null): void
    {
        $this->log(
            action: 'CONFIGURATION',
            level: 'WARNING',
            status: 'SUCCES',
            username: $username,
            ipAddress: $ipAddress,
            entityType: $entityType,
            entityId: $entityId,
            description: sprintf('L\'utilisateur %s a %s %s "%s"', $username, $operation, $entityType, $entityLabel),
            details: $details,
        );
    }

    public function logErreur(string $route, string $exceptionClass, string $message, ?string $username = null, ?string $ipAddress = null): void
    {
        $this->log(
            action: 'CONFIGURATION',
            level: 'ERROR',
            status: 'ECHEC',
            username: $username ?? 'Système',
            ipAddress: $ipAddress,
            entityType: null,
            entityId: null,
            description: sprintf('Erreur serveur sur %s : %s (%s)', $route, $exceptionClass, $message),
            details: [
                'route' => $route,
                'exception' => $exceptionClass,
                'message' => mb_substr($message, 0, 200),
            ],
        );
    }

    private function getLastHash(): ?string
    {
        try {
            $sql = 'SELECT hash FROM audit_log ORDER BY id DESC LIMIT 1';
            $result = $this->connection->fetchOne($sql);
            return $result ?: null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function computeHash(?string $previousHash, string $action, string $username, string $description, ?array $details): string
    {
        $data = ($previousHash ?? '0') . '|' . (new \DateTimeImmutable())->format('Y-m-d H:i:s') . '|' . $username . '|' . $action . '|' . $description . '|' . json_encode($details ?? [], JSON_UNESCAPED_UNICODE);
        return hash('sha256', $data);
    }
}