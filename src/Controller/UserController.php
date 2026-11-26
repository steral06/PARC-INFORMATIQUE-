<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\AuditLogger;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_SUPER_ADMIN')]
#[Route('/utilisateurs')]
class UserController extends AbstractController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {
    }

    #[Route('', name: 'app_user_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $search = trim((string) $request->query->get('q', ''));

        $qb = $userRepository->createQueryBuilder('u')
            ->orderBy('u.username', 'ASC');

        if ($search !== '') {
            $qb->andWhere('u.username LIKE :q')
               ->setParameter('q', '%' . $search . '%');
        }

        $users = $qb->getQuery()->getResult();

        return $this->render('user/index.html.twig', [
            'users' => $users,
            'current_search' => $search,
        ]);
    }

    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route('/new', name: 'app_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('user_new', $token)) {
                $this->addFlash('error', 'Jeton de sécurité CSRF invalide.');
                return $this->redirectToRoute('app_user_new');
            }

            $username = trim((string) $request->request->get('username'));
            $password = (string) $request->request->get('password');
            $role = (string) $request->request->get('role', 'ROLE_USER');
            $pin = trim((string) $request->request->get('pin', '1234'));

            if ($username !== '' && $password !== '') {
                $user = new User();
                $user->setUsername($username);
                $user->setRoles([$role]);
                $user->setPassword($hasher->hashPassword($user, $password));
                $user->setPinCode(str_pad(substr(preg_replace('/\D/', '', $pin), 0, 4), 4, '0'));
                $user->setPasswordChangedAt(new \DateTimeImmutable());

                $em->persist($user);
                $em->flush();

                $adminUser = $this->getUser()?->getUserIdentifier() ?? 'superadmin';
                $clientIp = $request->getClientIp() ?? '127.0.0.1';

                $this->auditLogger->logCreation(
                    entityType: 'User',
                    entityId: (string) $user->getId(),
                    entityLabel: $username . ' (' . $role . ')',
                    username: $adminUser,
                    ipAddress: $clientIp,
                    details: [
                        'username' => $username,
                        'role' => $role,
                    ]
                );

                $this->addFlash('success', 'Utilisateur "' . $username . '" créé avec succès.');
                return $this->redirectToRoute('app_user_index');
            }

            $this->addFlash('error', 'Veuillez remplir tous les champs obligatoires.');
        }

        return $this->render('user/new.html.twig');
    }

    /**
     * Réinitialise le mot de passe d'un utilisateur (génère un MDP temporaire aléatoire).
     * Accessible uniquement par le Superadmin.
     */
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route('/{id}/reset-password', name: 'app_user_reset_password', methods: ['POST'])]
    public function resetPassword(
        User $user,
        Request $request,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
    ): Response {
        if (!$this->isCsrfTokenValid('reset_password_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        // Empêcher le superadmin de se réinitialiser lui-même
        if ($user->getUserIdentifier() === $this->getUser()?->getUserIdentifier()) {
            $this->addFlash('danger', 'Vous ne pouvez pas réinitialiser votre propre mot de passe depuis ici. Utilisez "Modifier mon mot de passe".');
            return $this->redirectToRoute('app_user_index');
        }

        // Génération d'un mot de passe temporaire lisible : GTA-XXXX-YYYY
        $tempPassword = 'GTA-' . strtoupper(bin2hex(random_bytes(2))) . '-' . strtoupper(bin2hex(random_bytes(2)));

        $user->setPassword($hasher->hashPassword($user, $tempPassword));
        // Forcer l'expiration immédiate → l'utilisateur devra changer à la prochaine connexion
        $user->setPasswordChangedAt(new \DateTimeImmutable('-31 days'));

        $em->flush();

        $adminUser = $this->getUser()?->getUserIdentifier() ?? 'superadmin';
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        $this->auditLogger->logConfiguration(
            entityType: 'User',
            entityId: (string) $user->getId(),
            entityLabel: $user->getUserIdentifier(),
            username: $adminUser,
            ipAddress: $clientIp,
            operation: 'réinitialisé le mot de passe du compte',
            details: ['action' => 'RESET_PASSWORD_ADMIN']
        );

        $this->addFlash(
            'success',
            sprintf(
                'Mot de passe de « %s » réinitialisé. MDP temporaire : <code class="fw-bold fs-6 px-2 py-1 bg-warning-subtle text-dark rounded">%s</code> — Communiquez-le à l\'utilisateur, il devra le changer à la prochaine connexion.',
                htmlspecialchars($user->getUserIdentifier()),
                htmlspecialchars($tempPassword)
            )
        );

        return $this->redirectToRoute('app_user_index');
    }

    /**
     * Réinitialise le code PIN 2FA d'un utilisateur à la valeur par défaut (1234).
     * Accessible uniquement par le Superadmin.
     */
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route('/{id}/reset-pin', name: 'app_user_reset_pin', methods: ['POST'])]
    public function resetPin(
        User $user,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('reset_pin_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        $user->setPinCode('1234');
        $em->flush();

        $adminUser = $this->getUser()?->getUserIdentifier() ?? 'superadmin';
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        $this->auditLogger->logConfiguration(
            entityType: 'User',
            entityId: (string) $user->getId(),
            entityLabel: $user->getUserIdentifier(),
            username: $adminUser,
            ipAddress: $clientIp,
            operation: 'réinitialisé le code PIN 2FA du compte à 1234',
            details: ['action' => 'RESET_PIN_2FA']
        );

        $this->addFlash(
            'success',
            sprintf(
                'Code PIN 2FA de « %s » réinitialisé à <code class="fw-bold px-2 py-1 bg-secondary-subtle text-dark rounded">1234</code>. Demandez à l\'utilisateur de le modifier depuis son profil.',
                htmlspecialchars($user->getUserIdentifier())
            )
        );

        return $this->redirectToRoute('app_user_index');
    }

    /**
     * Supprime un utilisateur (Superadmin uniquement).
     * Empêche l'auto-suppression.
     */
    #[IsGranted('ROLE_SUPER_ADMIN')]
    #[Route('/{id}/delete', name: 'app_user_delete', methods: ['POST'])]
    public function delete(
        User $user,
        Request $request,
        EntityManagerInterface $em,
    ): Response {
        if (!$this->isCsrfTokenValid('delete_' . $user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token CSRF invalide.');
            return $this->redirectToRoute('app_user_index');
        }

        // Empêcher l'auto-suppression
        if ($user->getUserIdentifier() === $this->getUser()?->getUserIdentifier()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_user_index');
        }

        $username = $user->getUserIdentifier();
        $userId = (string) $user->getId();
        $em->remove($user);
        $em->flush();

        $adminUser = $this->getUser()?->getUserIdentifier() ?? 'superadmin';
        $clientIp = $request->getClientIp() ?? '127.0.0.1';

        $this->auditLogger->logSuppression(
            entityType: 'User',
            entityId: $userId,
            entityLabel: $username,
            username: $adminUser,
            ipAddress: $clientIp
        );

        $this->addFlash('success', 'Utilisateur "' . $username . '" supprimé définitivement.');

        return $this->redirectToRoute('app_user_index');
    }
}

