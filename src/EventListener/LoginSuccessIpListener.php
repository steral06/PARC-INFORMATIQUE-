<?php

namespace App\EventListener;

use App\Entity\PosteAutorise;
use App\Repository\PosteAutoriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
class LoginSuccessIpListener
{
    public function __construct(
        private readonly PosteAutoriseRepository $posteRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        $clientIp = $request->getClientIp() ?? '127.0.0.1';
        $user = $event->getUser();
        $now = new \DateTimeImmutable();

        $poste = $this->posteRepository->findOneBy(['adresseIp' => $clientIp]);

        if (!$poste) {
            // Première authentification sur cette machine : on enregistre son adresse IP
            $poste = new PosteAutorise();
            $poste->setAdresseIp($clientIp);
            $poste->setNom('Poste ' . ($clientIp === '127.0.0.1' ? 'Localhost' : $clientIp));
            $poste->setDescription(sprintf(
                'Enregistré automatiquement lors de la première connexion de [%s] le %s',
                $user->getUserIdentifier(),
                $now->format('d/m/Y à H:i:s')
            ));
            $poste->setIsAutorise(true);
            $poste->setDatePremiereConnexion($now);
            $poste->setDateDerniereConnexion($now);

            $this->entityManager->persist($poste);
            $this->entityManager->flush();

            $session = $this->requestStack->getSession();
            if ($session && method_exists($session, 'getFlashBag')) {
                $session->getFlashBag()->add(
                    'info',
                    "🎉 Première connexion détectée depuis ce poste (IP : {$clientIp}). Votre machine a été enregistrée avec succès."
                );
            }
        } else {
            // Machine déjà répertoriée : mise à jour de la dernière date de connexion
            // Seulement si la dernière mise à jour date de plus de 30 secondes pour éviter les écritures excessives
            $lastActivity = $poste->getDateDerniereActivite();
            if (!$lastActivity || (time() - $lastActivity->getTimestamp()) > 30) {
                $poste->setDateDerniereConnexion($now);
                $poste->setDateDerniereActivite($now);
                $this->entityManager->flush();
            }
        }
    }
}
