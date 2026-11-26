<?php

namespace App\Tests\Controller;

use App\Entity\PosteAutorise;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityAndIpControlTest extends WebTestCase
{
    private function prepareUsers(EntityManagerInterface $em, UserPasswordHasherInterface $hasher): void
    {
        $superadmin = $em->getRepository(User::class)->findOneBy(['username' => 'superadmin']);
        if (!$superadmin) {
            $superadmin = new User();
            $superadmin->setUsername('superadmin');
            $em->persist($superadmin);
        }
        $superadmin->setRoles(['ROLE_SUPER_ADMIN']);
        $superadmin->setPassword($hasher->hashPassword($superadmin, 'admin123'));
        $superadmin->setPinCode('1234');
        $superadmin->setPasswordChangedAt(new \DateTimeImmutable());

        $sharedUser = $em->getRepository(User::class)->findOneBy(['username' => 'utilisateur']);
        if (!$sharedUser) {
            $sharedUser = new User();
            $sharedUser->setUsername('utilisateur');
            $em->persist($sharedUser);
        }
        $sharedUser->setRoles(['ROLE_USER']);
        $sharedUser->setPassword($hasher->hashPassword($sharedUser, 'parc2026'));
        $sharedUser->setPinCode('1234');
        $sharedUser->setPasswordChangedAt(new \DateTimeImmutable());

        $em->flush();
    }

    private function complete2fa(object $client): void
    {
        $client->request('POST', '/2fa/verify', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['pin' => '1234']));
    }

    public function testLoginPageIsAccessible(): void
    {
        $client = static::createClient();
        $crawler = $client->request('GET', '/login');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('img[alt="GTAC2A-IARDT"]');
        $this->assertSelectorExists('input[name="_username"]');
        $this->assertSelectorExists('input[name="_password"]');
    }

    public function testUnauthenticatedUserRedirectedToLogin(): void
    {
        $client = static::createClient();
        $client->request('GET', '/');

        $this->assertResponseRedirects('/login');
    }

    public function testSharedUserLoginRecordsClientIpAndRequires2fa(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $this->prepareUsers($em, $hasher);

        // Supprimer 127.0.0.1 pour tester l'auto-enregistrement
        $existing = $em->getRepository(PosteAutorise::class)->findOneBy(['adresseIp' => '127.0.0.1']);
        if ($existing) {
            $em->remove($existing);
            $em->flush();
        }

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'utilisateur',
            '_password' => 'parc2026',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/');

        $client->followRedirect();
        // Redirigé vers /2fa/pin tant que le 2FA n'est pas validé
        $this->assertResponseRedirects('/2fa/pin');

        // Vérifier l'auto-enregistrement de l'IP
        $poste = $em->getRepository(PosteAutorise::class)->findOneBy(['adresseIp' => '127.0.0.1']);
        $this->assertNotNull($poste);
        $this->assertTrue($poste->isAutorise());
    }

    public function test2faVerificationWorkflow(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $this->prepareUsers($em, $hasher);

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'utilisateur',
            '_password' => 'parc2026',
        ]);
        $client->submit($form);

        // Mauvais code PIN
        $client->request('POST', '/2fa/verify', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['pin' => '0000']));
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertFalse($data['success']);

        // Bon code PIN
        $client->request('POST', '/2fa/verify', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['pin' => '1234']));
        $data = json_decode($client->getResponse()->getContent(), true);
        $this->assertTrue($data['success']);

        // Accès accordé au tableau de bord
        $client->request('GET', '/');
        $this->assertResponseIsSuccessful();
    }

    public function testSuperadminCanAccessPostesManagement(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $this->prepareUsers($em, $hasher);

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'superadmin',
            '_password' => 'admin123',
        ]);

        $client->submit($form);
        $this->complete2fa($client);

        // Superadmin visite /postes
        $client->request('GET', '/postes');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Contrôle Réseau & Postes Autorisés');
    }

    public function testSharedUserCannotAccessPostesManagement(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $this->prepareUsers($em, $hasher);

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'utilisateur',
            '_password' => 'parc2026',
        ]);

        $client->submit($form);
        $this->complete2fa($client);

        // Tentative d'accès à /postes par utilisateur standard -> 403 Forbidden
        $client->request('GET', '/postes');
        $this->assertResponseStatusCodeSame(403);
    }

    public function testBlockedIpPreventsSharedUserLogin(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $this->prepareUsers($em, $hasher);

        // Bloquer 127.0.0.1
        $poste = $em->getRepository(PosteAutorise::class)->findOneBy(['adresseIp' => '127.0.0.1']);
        if (!$poste) {
            $poste = new PosteAutorise();
            $poste->setAdresseIp('127.0.0.1');
            $poste->setNom('Poste Localhost');
            $em->persist($poste);
        }
        $poste->setIsAutorise(false);
        $em->flush();

        // Tentative de connexion par le compte partagé
        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'utilisateur',
            '_password' => 'parc2026',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/login');

        $crawler = $client->followRedirect();
        $this->assertSelectorTextContains('.alert-danger', 'Accès refusé');

        // Rétablir l'autorisation
        $poste->setIsAutorise(true);
        $em->flush();
    }

    public function testMonthlyPasswordExpirationForcesChange(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $this->prepareUsers($em, $hasher);

        // Forcer l'expiration du mot de passe (> 30 jours)
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'utilisateur']);
        $user->setPasswordChangedAt(new \DateTimeImmutable('-40 days'));
        $em->flush();

        $poste = $em->getRepository(PosteAutorise::class)->findOneBy(['adresseIp' => '127.0.0.1']);
        if (!$poste) {
            $poste = new PosteAutorise();
            $poste->setAdresseIp('127.0.0.1');
            $poste->setNom('Poste Localhost');
            $em->persist($poste);
        }
        $poste->setIsAutorise(true);
        $em->flush();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'utilisateur',
            '_password' => 'parc2026',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->complete2fa($client);

        // L'utilisateur tente d'aller sur l'accueil : redirection obligatoire vers /password/change
        $client->request('GET', '/');
        $this->assertResponseRedirects('/password/change');

        // Visite du formulaire de changement
        $crawler = $client->followRedirect();
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h4', 'Renouvellement Sécurisé');

        // Soumission du nouveau mot de passe
        $changeForm = $crawler->selectButton('Valider pour les 30 prochains jours')->form([
            'current_password' => 'parc2026',
            'new_password' => 'nouveau_parc2026!',
            'confirm_password' => 'nouveau_parc2026!',
        ]);

        $client->submit($changeForm);
        $this->assertResponseRedirects('/');

        // Accès rétabli à l'accueil
        $client->followRedirect();
        $this->assertResponseIsSuccessful();
    }

    public function testProfilePageAndPinUpdate(): void
    {
        $client = static::createClient();
        $container = static::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $hasher = $container->get(UserPasswordHasherInterface::class);

        $this->prepareUsers($em, $hasher);

        $poste = $em->getRepository(PosteAutorise::class)->findOneBy(['adresseIp' => '127.0.0.1']);
        if (!$poste) {
            $poste = new PosteAutorise();
            $poste->setAdresseIp('127.0.0.1');
            $poste->setNom('Poste Localhost');
            $em->persist($poste);
        }
        $poste->setIsAutorise(true);
        $em->flush();

        $crawler = $client->request('GET', '/login');
        $form = $crawler->selectButton('Se connecter')->form([
            '_username' => 'utilisateur',
            '_password' => 'parc2026',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/');
        $client->followRedirect();
        $this->complete2fa($client);

        // Accès à la page profil
        $crawler = $client->request('GET', '/profil');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mon Profil & Sécurité');
        $this->assertSelectorTextContains('h4', 'Utilisateur');

        // Mise à jour du code PIN 2FA
        $pinForm = $crawler->selectButton('Enregistrer le PIN')->form([
            'pin_code' => '5678',
        ]);
        $client->submit($pinForm);
        $this->assertResponseRedirects('/profil');
        $client->followRedirect();
        $this->assertResponseIsSuccessful();

        // Vérifier que le PIN a bien été persisté
        $user = $em->getRepository(User::class)->findOneBy(['username' => 'utilisateur']);
        $this->assertSame('5678', $user->getPinCode());
    }
}

