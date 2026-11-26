<?php

namespace App\EventListener;

use App\Repository\PosteAutoriseRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: RequestEvent::class, priority: 10)]
class RequestIpRestrictionListener
{
    public function __construct(
        private readonly PosteAutoriseRepository $posteRepository,
        private readonly TokenStorageInterface $tokenStorage,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        // Ignorer les assets, le profiler et la page de login
        if (str_starts_with($path, '/login') || str_starts_with($path, '/auth/ping') || str_starts_with($path, '/assets') || str_starts_with($path, '/_')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return;
        }

        $user = $token->getUser();
        // Le superadmin n'est jamais restreint
        if ($user->getUserIdentifier() === 'superadmin') {
            return;
        }

        $clientIp = $request->getClientIp() ?? '127.0.0.1';
        $poste = $this->posteRepository->findOneBy(['adresseIp' => $clientIp]);

        if ($poste && !$poste->isAutorise()) {
            // Déconnexion immédiate
            $this->tokenStorage->setToken(null);
            if ($request->hasSession()) {
                $session = $request->getSession();
                $session->getFlashBag()->add(
                    'danger',
                    sprintf('Accès révoqué : votre poste de travail (IP : %s) a été restreint par le Superadmin.', $clientIp)
                );
                $session->invalidate();
            }

            $loginUrl = $this->urlGenerator->generate('app_login');
            $event->setResponse(new RedirectResponse($loginUrl));
        }
    }
}
