<?php

namespace App\EventListener;

use App\Service\AuditLogger;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: ExceptionEvent::class)]
class AuditExceptionListener
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly RequestStack $requestStack,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();
        $code = $exception->getCode();

        // Only log HTTP 500+ errors
        if ($code < 500) {
            // Check if it's an HttpException with status code >= 500
            if (method_exists($exception, 'getStatusCode')) {
                $statusCode = $exception->getStatusCode();
                if ($statusCode < 500) {
                    return;
                }
            } else {
                return;
            }
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $route = $request->get('_route') ?? $request->getPathInfo();
        $ip = $request->getClientIp() ?? '127.0.0.1';

        $token = $this->tokenStorage->getToken();
        $username = 'Système';
        if ($token && $token->getUser() && method_exists($token->getUser(), 'getUserIdentifier')) {
            $username = $token->getUser()->getUserIdentifier();
        }

        $message = $exception->getMessage();
        if (mb_strlen($message) > 200) {
            $message = mb_substr($message, 0, 200) . '...';
        }

        $this->auditLogger->logErreur(
            route: $route,
            exceptionClass: get_class($exception),
            message: $message,
            username: $username,
            ipAddress: $ip,
        );
    }
}