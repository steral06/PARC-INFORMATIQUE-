<?php

namespace App\Controller;

use App\Entity\WebAuthnCredential;
use App\Repository\UserRepository;
use App\Repository\WebAuthnCredentialRepository;
use App\Service\WebAuthnManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/webauthn', name: 'app_webauthn_')]
class WebAuthnController extends AbstractController
{
    public function __construct(
        private readonly WebAuthnManager $webAuthn,
        private readonly EntityManagerInterface $em,
        private readonly WebAuthnCredentialRepository $credRepo,
        private readonly UserRepository $userRepo,
    ) {}

    // ── Registration (SUPER_ADMIN only) ───────────────────────────────────────

    #[Route('/register/options', name: 'register_options', methods: ['GET'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function registerOptions(): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user    = $this->getUser();
        $options = $this->webAuthn->generateRegistrationOptions(
            (string) $user->getId(),
            $user->getUserIdentifier()
        );
        return $this->json($options);
    }

    #[Route('/register/verify', name: 'register_verify', methods: ['POST'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function registerVerify(Request $request): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json(['success' => false, 'error' => 'Données invalides.'], 400);
        }

        try {
            $result = $this->webAuthn->verifyRegistration($data);
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }

        // Reject duplicate credentials
        if ($this->credRepo->findByCredentialId($result['credentialId'])) {
            return $this->json(['success' => false, 'error' => 'Cet appareil est déjà enregistré.'], 400);
        }

        $deviceName = $this->detectDeviceName($request->headers->get('User-Agent', ''));

        $credential = (new WebAuthnCredential())
            ->setUser($user)
            ->setCredentialId($result['credentialId'])
            ->setPublicKey($result['publicKey'])
            ->setAlgorithm($result['algorithm'])
            ->setSignCount($result['signCount'])
            ->setDeviceName($deviceName);

        $this->em->persist($credential);
        $this->em->flush();

        return $this->json(['success' => true, 'deviceName' => $deviceName]);
    }

    #[Route('/credentials/{id}', name: 'delete_credential', methods: ['DELETE'])]
    #[IsGranted('ROLE_SUPER_ADMIN')]
    public function deleteCredential(int $id): JsonResponse
    {
        /** @var \App\Entity\User $user */
        $user       = $this->getUser();
        $credential = $this->credRepo->find($id);

        if (!$credential || $credential->getUser() !== $user) {
            return $this->json(['success' => false, 'error' => 'Credential introuvable.'], 404);
        }

        $this->em->remove($credential);
        $this->em->flush();

        return $this->json(['success' => true]);
    }

    // ── Authentication (PUBLIC) ───────────────────────────────────────────────

    #[Route('/login/options', name: 'login_options', methods: ['GET'])]
    public function loginOptions(Request $request): JsonResponse
    {
        $username = trim((string) $request->query->get('username', ''));
        if ($username === '') {
            return $this->json(['error' => 'Paramètre username requis.'], 400);
        }

        $user = $this->userRepo->findOneBy(['username' => $username]);

        // Do not reveal whether the user exists — return "unavailable" either way
        if (!$user || !in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return $this->json(['available' => false, 'challenge' => '', 'allowCredentials' => []]);
        }

        $credentials = $this->credRepo->findByUser($user);
        if (empty($credentials)) {
            return $this->json(['available' => false, 'challenge' => '', 'allowCredentials' => []]);
        }

        $options              = $this->webAuthn->generateAuthenticationOptions($credentials);
        $options['available'] = true;

        // Store username so loginVerify can find the user without leaking it via POST body
        $request->getSession()->set('webauthn_login_username', $username);

        return $this->json($options);
    }

    #[Route('/login/verify', name: 'login_verify', methods: ['POST'])]
    public function loginVerify(
        Request $request,
        TokenStorageInterface $tokenStorage,
        EventDispatcherInterface $eventDispatcher,
    ): JsonResponse {
        $username = $request->getSession()->get('webauthn_login_username');
        $request->getSession()->remove('webauthn_login_username');

        if (!$username) {
            return $this->json(['success' => false, 'error' => 'Session expirée, recommencez.'], 400);
        }

        $user = $this->userRepo->findOneBy(['username' => $username]);
        if (!$user || !in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return $this->json(['success' => false, 'error' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['success' => false, 'error' => 'Données invalides.'], 400);
        }

        $credentialId = $data['id'] ?? '';
        $credential   = $this->credRepo->findByCredentialId($credentialId);

        if (!$credential || $credential->getUser() !== $user) {
            return $this->json(['success' => false, 'error' => 'Credential non reconnu.'], 400);
        }

        try {
            $newSignCount = $this->webAuthn->verifyAuthentication(
                $data,
                $credential->getPublicKey(),
                $credential->getSignCount()
            );
        } catch (\RuntimeException $e) {
            return $this->json(['success' => false, 'error' => $e->getMessage()], 400);
        }

        // Persist updated signCount and last-used timestamp
        $credential->setSignCount($newSignCount);
        $credential->setLastUsedAt(new \DateTimeImmutable());
        $this->em->flush();

        // Programmatically log the user in (WebAuthn supersedes 2FA PIN)
        $token = new UsernamePasswordToken($user, 'main', $user->getRoles());
        $tokenStorage->setToken($token);
        $request->getSession()->set('_security_main', serialize($token));
        // Mark 2FA PIN as already passed so the PIN screen is skipped
        $request->getSession()->set('2fa_passed', true);

        $event = new InteractiveLoginEvent($request, $token);
        $eventDispatcher->dispatch($event, 'security.interactive_login');

        return $this->json([
            'success'  => true,
            'redirect' => $this->generateUrl('app_home'),
        ]);
    }

    // ── Internal helper ───────────────────────────────────────────────────────

    private function detectDeviceName(string $ua): string
    {
        if (str_contains($ua, 'Android'))              { return 'Android'; }
        if (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) { return 'iOS'; }
        if (str_contains($ua, 'Windows'))              { return 'Windows Hello'; }
        if (str_contains($ua, 'Macintosh'))            { return 'macOS Touch ID'; }
        if (str_contains($ua, 'Linux'))                { return 'Linux'; }
        return 'Appareil inconnu';
    }
}
