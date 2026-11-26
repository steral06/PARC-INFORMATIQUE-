<?php

namespace App\EventListener;

use App\Repository\PosteAutoriseRepository;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Event\CheckPassportEvent;

#[AsEventListener(event: CheckPassportEvent::class, priority: 100)]
class CheckPassportIpRestrictionListener
{
    public function __construct(
        private readonly PosteAutoriseRepository $posteRepository,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(CheckPassportEvent $event): void
    {
        $passport = $event->getPassport();
        $user = $passport->getUser();

        // Le Superadmin a toujours un droit de connexion pour administrer et débloquer
        if ($user->getUserIdentifier() === 'superadmin') {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();
        if (!$request) {
            return;
        }

        $clientIp = $request->getClientIp() ?? '127.0.0.1';
        $poste = $this->posteRepository->findOneBy(['adresseIp' => $clientIp]);

        // Si la machine a été explicitement restreinte par le Superadmin
        if ($poste && !$poste->isAutorise()) {
            throw new CustomUserMessageAuthenticationException(
                sprintf('Accès refusé : votre poste de travail (IP : %s) a été restreint par le Superadmin.', $clientIp)
            );
        }
    }
}
