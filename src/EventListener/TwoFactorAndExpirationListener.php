<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

#[AsEventListener(event: RequestEvent::class, priority: 5)]
class TwoFactorAndExpirationListener
{
    public function __construct(
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

        // Routes exemptées
        $exemptedPrefixes = [
            '/login',
            '/logout',
            '/2fa/pin',
            '/2fa/verify',
            '/auth/ping',
            '/assets',
            '/_',
        ];

        foreach ($exemptedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $token = $this->tokenStorage->getToken();
        if (!$token || !$token->getUser()) {
            return;
        }

        /** @var User|null $user */
        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $session = $request->getSession();

        // 1. Étape 2FA Code PIN
        if ($session->get('2fa_passed') !== true) {
            $pinUrl = $this->urlGenerator->generate('app_2fa_pin');
            $event->setResponse(new RedirectResponse($pinUrl));
            return;
        }

        // 2. Étape Expiration du mot de passe (obligatoire chaque mois)
        if ($user->isPasswordExpired() && !str_starts_with($path, '/password/change')) {
            $changeUrl = $this->urlGenerator->generate('app_password_change');
            $event->setResponse(new RedirectResponse($changeUrl));
        }
    }
}
