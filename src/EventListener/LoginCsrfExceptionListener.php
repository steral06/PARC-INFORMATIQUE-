<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\Exception\InvalidCsrfTokenException;

#[AsEventListener(event: ExceptionEvent::class)]
class LoginCsrfExceptionListener
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {}

    public function __invoke(ExceptionEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $exception = $event->getThrowable();

        // Only handle InvalidCsrfTokenException on login route
        if (!$exception instanceof InvalidCsrfTokenException) {
            return;
        }

        $request = $event->getRequest();
        if (!$request) {
            return;
        }

        $route = $request->get('_route');
        if ($route !== 'app_login') {
            return;
        }

        // Convert to a redirect back to login with error message
        $url = $this->urlGenerator->generate('app_login');
        $response = new RedirectResponse($url);

        $session = $request->getSession();
        if ($session) {
            $session->getFlashBag()->add('error', 'Jeton CSRF invalide. Veuillez rafraîchir la page et réessayer.');
        }

        $event->setResponse($response);
    }
}