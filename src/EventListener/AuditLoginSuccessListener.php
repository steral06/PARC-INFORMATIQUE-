<?php

namespace App\EventListener;

use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
class AuditLoginSuccessListener
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RequestStack $requestStack,
    ) {}

    public function __invoke(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $user = $event->getUser();
        $ip = $request->getClientIp() ?? '127.0.0.1';

        if (!$user || !method_exists($user, 'getUserIdentifier')) {
            return;
        }

        $this->auditLogger->logConnexion($user->getUserIdentifier(), $ip, true);
    }
}