<?php

namespace App\EventListener;

use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;

#[AsEventListener(event: LoginFailureEvent::class)]
class AuditLoginFailureListener
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RequestStack $requestStack,
    ) {}

    public function __invoke(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        $ip = $request->getClientIp() ?? '127.0.0.1';
        $username = $request->request->get('_username') ?? 'Inconnu';
        $exception = $event->getException();

        // Message lisible selon le type d'erreur
        if ($exception instanceof UserNotFoundException) {
            $reason = 'Utilisateur inexistant';
        } elseif ($exception instanceof BadCredentialsException) {
            $reason = 'Mot de passe incorrect';
        } elseif ($exception instanceof InvalidCsrfTokenException) {
            $reason = 'Session expirée ou jeton de sécurité invalide. Veuillez rafraîchir la page.';
        } else {
            $reason = $exception ? $exception->getMessage() : 'Échec inconnu';
        }

        $this->auditLogger->logEchecConnexion($username, $ip, $reason);
    }
}