<?php

namespace App\Controller;

use App\Repository\PosteAutoriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils, Request $request): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        $response = $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'client_ip' => $clientIp,
        ]);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route(path: '/auth/ping', name: 'app_auth_ping', methods: ['GET', 'POST'])]
    public function ping(
        Request $request,
        PosteAutoriseRepository $posteRepository,
        EntityManagerInterface $entityManager,
        TokenStorageInterface $tokenStorage,
    ): JsonResponse {
        $user = $this->getUser();
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        if (!$user) {
            return new JsonResponse([
                'authenticated' => false,
                'blocked' => false,
            ]);
        }

        $poste = $posteRepository->findOneBy(['adresseIp' => $clientIp]);
        $now = new \DateTimeImmutable();

        if ($poste) {
            // Mettre à jour l'activité en temps réel
            $poste->setDateDerniereActivite($now);
            $entityManager->flush();

            // Si la machine a été restreinte et que l'utilisateur n'est pas le Superadmin
            if (!$poste->isAutorise() && $user->getUserIdentifier() !== 'superadmin') {
                $tokenStorage->setToken(null);
                if ($request->hasSession()) {
                    $session = $request->getSession();
                    $session->getFlashBag()->add('danger', sprintf('Accès révoqué : votre poste (IP : %s) a été restreint par le Superadmin.', $clientIp));
                    $session->invalidate();
                }

                return new JsonResponse([
                    'authenticated' => false,
                    'blocked' => true,
                    'message' => 'Accès restreint par le Superadmin.',
                    'redirect' => $this->generateUrl('app_login'),
                ]);
            }
        }

        return new JsonResponse([
            'authenticated' => true,
            'blocked' => false,
            'ip' => $clientIp,
            'user' => $user->getUserIdentifier(),
        ]);
    }
}
