<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\PosteAutoriseRepository;
use App\Repository\WebAuthnCredentialRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProfileController extends AbstractController
{
    #[Route('/profil', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        PosteAutoriseRepository $posteRepository,
        UserPasswordHasherInterface $passwordHasher,
        WebAuthnCredentialRepository $credRepo,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $clientIp = $request->getClientIp() ?? '127.0.0.1';
        $poste = $posteRepository->findOneBy(['adresseIp' => $clientIp]);

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            // --- Changement du code PIN 2FA ---
            if ($action === 'update_pin') {
                $pin = trim((string) $request->request->get('pin_code'));

                if (preg_match('/^\d{4}$/', $pin)) {
                    $user->setPinCode($pin);
                    $entityManager->flush();
                    $this->addFlash('success', 'Votre code PIN 2FA a été modifié avec succès (' . $pin . ').');
                } else {
                    $this->addFlash('danger', 'Le code PIN 2FA doit être composé d\'exactement 4 chiffres.');
                }

                return $this->redirectToRoute('app_profile');
            }

            // --- Changement du mot de passe depuis le profil ---
            if ($action === 'update_password') {
                $currentPassword  = (string) $request->request->get('current_password');
                $newPassword      = (string) $request->request->get('new_password');
                $confirmPassword  = (string) $request->request->get('confirm_password');

                $error = null;

                if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                    $error = 'Le mot de passe actuel est incorrect.';
                } elseif (strlen($newPassword) < 6) {
                    $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'La confirmation ne correspond pas au nouveau mot de passe.';
                } elseif ($newPassword === $currentPassword) {
                    $error = 'Le nouveau mot de passe doit être différent de l\'ancien.';
                }

                if ($error) {
                    $this->addFlash('danger', $error);
                } else {
                    $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                    $user->setPasswordChangedAt(new \DateTimeImmutable());
                    $entityManager->flush();
                    $this->addFlash('success', 'Votre mot de passe a été modifié avec succès. Votre accès est sécurisé pour les 30 prochains jours.');
                }

                return $this->redirectToRoute('app_profile');
            }
        }

        $daysLeft = $user->getDaysUntilPasswordExpiration();
        $daysPassed = max(0, 30 - $daysLeft);
        $percentUsed = min(100, (int) round(($daysPassed / 30) * 100));

        return $this->render('profile/index.html.twig', [
            'user'                 => $user,
            'client_ip'           => $clientIp,
            'poste'               => $poste,
            'days_left'           => $daysLeft,
            'days_passed'         => $daysPassed,
            'percent_used'        => $percentUsed,
            'webauthn_credentials' => $this->isGranted('ROLE_SUPER_ADMIN') ? $credRepo->findByUser($user) : [],
        ]);
    }
}
