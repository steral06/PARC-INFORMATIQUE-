<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class PasswordChangeController extends AbstractController
{
    #[Route('/password/change', name: 'app_password_change', methods: ['GET', 'POST'])]
    public function change(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var User $user */
        $user = $this->getUser();
        $isExpired = $user->isPasswordExpired();

        if ($request->isMethod('POST')) {
            $currentPassword = (string) $request->request->get('current_password');
            $newPassword = (string) $request->request->get('new_password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            $error = null;

            if (!$passwordHasher->isPasswordValid($user, $currentPassword)) {
                $error = 'Le mot de passe actuel est incorrect.';
            } elseif (strlen($newPassword) < 6) {
                $error = 'Le nouveau mot de passe doit contenir au moins 6 caractères.';
            } elseif ($newPassword !== $confirmPassword) {
                $error = 'La confirmation ne correspond pas au nouveau mot de passe.';
            } elseif ($newPassword === $currentPassword) {
                $error = 'Le nouveau mot de passe doit être différent de l\'ancien mot de passe.';
            }

            if ($error) {
                $this->addFlash('danger', $error);
            } else {
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $user->setPasswordChangedAt(new \DateTimeImmutable());
                $entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été modifié avec succès. Votre accès est sécurisé pour les 30 prochains jours.');

                return $this->redirectToRoute('app_home');
            }
        }

        $response = $this->render('security/change_password.html.twig', [
            'is_expired' => $isExpired,
            'days_left' => $user->getDaysUntilPasswordExpiration(),
        ]);
        $response->headers->set('Cache-Control', 'no-cache, no-store, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        $response->headers->set('Expires', '0');

        return $response;
    }
}
