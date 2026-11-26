<?php

namespace App\EventListener;

use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
class AuditLogoutListener
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RequestStack $requestStack,
    ) {}

    public function __invoke(LogoutEvent $event): void
    {
        $request = $event->getRequest();
        $token = $event->getToken();
        $ip = $request->getClientIp() ?? '127.0.0.1';

        if (!$token || !$token->getUser() || !method_exists($token->getUser(), 'getUserIdentifier')) {
            return;
        }

        $this->auditLogger->logDeconnexion($token->getUser()->getUserIdentifier(), $ip);
    }
}