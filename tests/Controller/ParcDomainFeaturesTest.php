<?php

namespace App\Tests\Controller;

use App\Entity\CategorieEquipement;
use App\Entity\Equipement;
use App\Entity\Lieu;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class ParcDomainFeaturesTest extends WebTestCase
{
    private function authenticateUser(object $client): User
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);

        $user = $em->getRepository(User::class)->findOneBy(['username' => 'superadmin']);
        if (!$user) {
            $user = new User();
            $user->setUsername('superadmin');
            $em->persist($user);
        }
        $user->setRoles(['ROLE_SUPER_ADMIN']);
        $user->setPassword($hasher->hashPassword($user, 'admin123'));
        $user->setPinCode('1234');
        $user->setPasswordChangedAt(new \DateTimeImmutable());
        $em->flush();

        $client->loginUser($user);
        // Compléter le 2FA
        $client->request('POST', '/2fa/verify', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['pin' => '1234']));

        return $user;
    }

    public function testDashboardRendersWithWatermarkAndMetrics(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $crawler = $client->request('GET', '/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('.gta-watermark-bg img');
        $this->assertSelectorTextContains('h1', 'Tableau de bord');
    }

    public function testEquipementListAndCreation(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $client->request('GET', '/equipements');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Inventaire des Équipements');

        // Création d'un nouvel équipement
        $crawler = $client->request('GET', '/equipements/new');
        $this->assertResponseIsSuccessful();

        $sn = 'TEST-SN-' . uniqid();
        $form = $crawler->selectButton('Enregistrer l\'équipement')->form([
            'equipement[nom]' => 'PC Test GTA DSI',
            'equipement[numeroSerie]' => $sn,
            'equipement[marque]' => 'Dell',
            'equipement[modele]' => 'OptiPlex 7090',
            'equipement[statut]' => 'En service',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects();
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'PC Test GTA DSI');
    }

    public function testCategoriesListAndCreation(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $client->request('GET', '/categories');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Catégories d\'Équipements');

        $crawler = $client->request('GET', '/categories/new');
        $catNom = 'Accessoires ' . uniqid();
        $form = $crawler->selectButton('Enregistrer la catégorie')->form([
            'categorie_equipement[nom_cat]' => $catNom,
            'categorie_equipement[description]' => 'Souris, claviers, casques',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/categories');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', $catNom);
    }

    public function testLieuxListAndCreation(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $client->request('GET', '/lieux');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Lieux & Salles');

        $crawler = $client->request('GET', '/lieux/new');
        $lieuNom = 'Salle Visio ' . uniqid();
        $form = $crawler->selectButton('Enregistrer le lieu')->form([
            'lieu[nom_lieu]' => $lieuNom,
            'lieu[description]' => 'Salle de conférence 3ème étage',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/lieux');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', $lieuNom);
    }

    public function testAttributionsCycle(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $eq = $em->getRepository(Equipement::class)->findOneBy([]);
        if (!$eq) {
            $eq = new Equipement();
            $eq->setNom('Test Laptop');
            $eq->setNumeroSerie('SN-ATT-TEST-' . uniqid());
            $eq->setMarque('Dell');
            $eq->setModele('5420');
            $em->persist($eq);
            $em->flush();
        }

        $crawler = $client->request('GET', '/attributions/new?equipement_id=' . $eq->getId());
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Valider l\'attribution')->form([
            'attribution[equipement]' => (string) $eq->getId(),
            'attribution[beneficiaireNom]' => 'Jean Dupont',
            'attribution[beneficiaireService]' => 'Comptabilité GTA',
            'attribution[dateDebut]' => '2026-10-05T09:00',
            'attribution[statut]' => 'En cours',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/attributions');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'Jean Dupont');
    }

    public function testMaintenanceWorkflow(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $eq = $em->getRepository(Equipement::class)->findOneBy([]);
        if (!$eq) {
            $eq = new Equipement();
            $eq->setNom('Test Server');
            $eq->setNumeroSerie('SN-MAINT-' . uniqid());
            $eq->setMarque('Dell');
            $eq->setModele('PowerEdge');
            $em->persist($eq);
            $em->flush();
        }

        $crawler = $client->request('GET', '/maintenances/new?equipement_id=' . $eq->getId());
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Créer le ticket de maintenance')->form([
            'ticket_maintenance[equipement]' => (string) $eq->getId(),
            'ticket_maintenance[titre]' => 'Panne Disque Dur Test',
            'ticket_maintenance[description]' => 'Bruit anormal au démarrage',
            'ticket_maintenance[priorite]' => 'Haute',
            'ticket_maintenance[statut]' => 'Nouveau',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/maintenances');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'Panne Disque Dur Test');
    }

    public function testUsersList(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $client->request('GET', '/utilisateurs');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Comptes Utilisateurs');
        $this->assertSelectorTextContains('body', 'superadmin');
    }

    public function testRapportPageRendersValuationAndAgingAudit(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $client->request('GET', '/rapports');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Centre de Rapports');
        $this->assertSelectorTextContains('body', 'Valorisation Globale');
        $this->assertSelectorTextContains('body', 'Générateur de Rapport Personnalisé');

        // Test export Excel
        $client->request('GET', '/rapports/export?scope=equipements&format=excel');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'application/vnd.ms-excel; charset=UTF-8');

        // Test export CSV
        $client->request('GET', '/rapports/export?scope=equipements&format=csv');
        $this->assertResponseIsSuccessful();
        $this->assertResponseHeaderSame('content-type', 'text/csv; charset=UTF-8');
    }

    public function testComposantListAndCreation(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $client->request('GET', '/composants');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Composants & Pièces Détachées');

        // Création d'un nouveau composant
        $crawler = $client->request('GET', '/composants/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer le composant')->form([
            'composant[nom]' => 'Barrette RAM 16Go DDR4 Kingston Test',
            'composant[type]' => 'Mémoire RAM',
            'composant[numeroSerie]' => 'RAM-' . uniqid(),
            'composant[quantite]' => '2',
            'composant[statut]' => 'En stock',
            'composant[prixAchat]' => '45000',
        ]);
        $client->submit($form);
        $this->assertResponseRedirects('/composants');
        $client->followRedirect();

        $this->assertSelectorTextContains('body', 'Barrette RAM 16Go DDR4 Kingston Test');
    }

    public function testEquipementDeletionDoesNotCrashAttributions(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $em = static::getContainer()->get(EntityManagerInterface::class);

        // Créer un équipement
        $eq = new Equipement();
        $eq->setNom('Machine Test Pour Suppression');
        $eq->setNumeroSerie('SN-DEL-' . uniqid());
        $eq->setMarque('HP');
        $eq->setModele('EliteBook');
        $em->persist($eq);

        // Créer une attribution liée
        $att = new \App\Entity\Attribution();
        $att->setEquipement($eq);
        $att->setBeneficiaireNom('Employé Test Intégrité');
        $att->setBeneficiaireService('Sinistres');
        $att->setStatut('En cours');
        $em->persist($att);
        $em->flush();

        $eqId = $eq->getId();

        // Récupérer le token CSRF depuis la page des équipements
        $crawler = $client->request('GET', '/equipements');
        $this->assertResponseIsSuccessful();
        $button = $crawler->filter('button[data-delete-url*="/equipements/' . $eqId . '/delete"]');
        $token = $button->attr('data-token');

        // Supprimer l'équipement
        $client->request('POST', '/equipements/' . $eqId . '/delete', ['_token' => $token]);
        $this->assertResponseRedirects('/equipements');

        // Vérifier que la page des attributions fonctionne toujours sans crash 500 / EntityNotFoundException
        $client->request('GET', '/attributions');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('body', 'Employé Test Intégrité');
    }

    public function testEquipementCreationWithNullNumeroSerie(): void
    {
        $client = static::createClient();
        $this->authenticateUser($client);

        $crawler = $client->request('GET', '/equipements/new');
        $this->assertResponseIsSuccessful();

        $form = $crawler->selectButton('Enregistrer l\'équipement')->form([
            'equipement[nom]' => 'Écran Dell UltraSharp Sans Numéro Série',
            'equipement[numeroSerie]' => '', // Champ laissé vide (nullable)
            'equipement[modele]' => 'U2723QE',
            'equipement[statut]' => 'En service',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects();
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Écran Dell UltraSharp Sans Numéro Série');
    }

    public function testUserProfilePageAndPinChange(): void
    {
        $client = static::createClient();
        $user = $this->authenticateUser($client);

        // Accès à la page profil
        $crawler = $client->request('GET', '/profil');
        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('h1', 'Mon Profil & Sécurité');
        $this->assertSelectorTextContains('body', 'Administrateur Système DSI');

        // Modification du code PIN 2FA
        $form = $crawler->selectButton('Enregistrer le nouveau PIN')->form([
            'current_pin' => '1234',
            'new_pin' => '9876',
            'confirm_pin' => '9876',
        ]);

        $client->submit($form);
        $this->assertResponseRedirects('/profil');
        $client->followRedirect();

        $this->assertResponseIsSuccessful();
        $this->assertSelectorTextContains('.alert-success', 'Votre code PIN 2FA a été modifié avec succès.');

        // Vérifier en base que le code PIN a été mis à jour
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();
        $updatedUser = $em->getRepository(User::class)->findOneBy(['username' => 'superadmin']);
        $this->assertSame('9876', $updatedUser->getPinCode());
    }
}
