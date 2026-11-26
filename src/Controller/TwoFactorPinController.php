<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\AuditLogger;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class TwoFactorPinController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    #[Route('/2fa/pin', name: 'app_2fa_pin', methods: ['GET'])]
    public function pin(Request $request): Response
    {
        $session = $request->getSession();
        if ($session->get('2fa_passed') === true) {
            /** @var User $user */
            $user = $this->getUser();
            if ($user && $user->isPasswordExpired()) {
                return $this->redirectToRoute('app_password_change');
            }
            return $this->redirectToRoute('app_home');
        }

        $response = $this->render('security/two_factor_pin.html.twig');
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }

    #[Route('/2fa/verify', name: 'app_2fa_verify', methods: ['POST'])]
    public function verify(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        $ip = $request->getClientIp() ?? '127.0.0.1';

        if (!$user) {
            return new JsonResponse(['success' => false, 'message' => 'Session expirée.'], 401);
        }

        $content = json_decode($request->getContent(), true);
        $pin = $content['pin'] ?? $request->request->get('pin');
        $pin = trim((string) $pin);

        $expectedPin = $user->getPinCode() ?? '1234';

        if ($pin !== $expectedPin) {
            $this->auditLogger->log(
                action: 'ECHEC_CONNEXION',
                level: 'WARNING',
                status: 'ECHEC',
                username: $user->getUserIdentifier(),
                ipAddress: $ip,
                entityType: null,
                entityId: null,
                description: sprintf('Code PIN incorrect pour l\'utilisateur %s depuis l\'adresse IP %s', $user->getUserIdentifier(), $ip),
                details: null,
            );

            return new JsonResponse([
                'success' => false,
                'message' => 'Code PIN incorrect. Veuillez réessayer.',
            ]);
        }

        // 2FA validé avec succès
        $session = $request->getSession();
        $session->set('2fa_passed', true);
        $session->set('2fa_passed_at', time());

        $this->auditLogger->log(
            action: 'CONNEXION',
            level: 'INFO',
            status: 'SUCCES',
            username: $user->getUserIdentifier(),
            ipAddress: $ip,
            entityType: null,
            entityId: null,
            description: sprintf('L\'utilisateur %s a validé son code PIN 2FA depuis l\'adresse IP %s', $user->getUserIdentifier(), $ip),
            details: null,
        );

        // Vérification de l'expiration du mot de passe (obligatoire chaque mois)
        $redirectUrl = $user->isPasswordExpired()
            ? $this->generateUrl('app_password_change')
            : $this->generateUrl('app_home');

        $response = new JsonResponse([
            'success' => true,
            'message' => 'Code PIN validé avec succès.',
            'redirect' => $redirectUrl,
        ]);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate');
        return $response;
    }
}