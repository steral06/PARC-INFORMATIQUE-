<?php

namespace App\EventListener;

use App\Service\AuditLogger;
use Doctrine\DBAL\Connection;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

#[AsEventListener(event: CheckPassportEvent::class, priority: 50)]
class AuditIpRefusedListener
{
    private const RATE_LIMIT_SECONDS = 60;

    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly Connection $connection,
        private readonly RequestStack $requestStack,
    ) {}

    public function __invoke(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        $user = $passport->getUser();

        // Le Superadmin a toujours un droit de connexion pour administrer et débloquer
        if ($user && method_exists($user, 'getUserIdentifier') && $user->getUserIdentifier() === 'superadmin') {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        // Check rate limiting: only log once per IP per minute
        if ($this->isRateLimited($clientIp)) {
            return;
        }

        $username = $user && method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : 'Anonyme';

        // Log the access refused event
        $this->auditLogger->logAccesRefuse($clientIp, $username);

        // Record this IP for rate limiting
        $this->recordIpAttempt($clientIp);
    }

    private function isRateLimited(string $ip): bool
    {
        try {
            $since = (new \DateTimeImmutable())->modify('-' . self::RATE_LIMIT_SECONDS . ' seconds')->format('Y-m-d H:i:s');
            $sql = 'SELECT COUNT(*) FROM audit_log WHERE ip_address = ? AND action = ? AND created_at > ?';
            $count = $this->connection->fetchOne($sql, [$ip, 'ACCES_REFUSE', $since]);
            return ($count ?? 0) > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    private function recordIpAttempt(string $ip): void
    {
        // The audit log entry itself serves as the record
        // No additional storage needed
    }
}