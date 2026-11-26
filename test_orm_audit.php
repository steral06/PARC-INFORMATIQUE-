<?php

require 'vendor/autoload.php';

$kernel = new \App\Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();

$em = $container->get('doctrine.orm.entity_manager');

// Test 1: Update existing entity
$equipement = $em->getRepository(\App\Entity\Equipement::class)->find(1);
if ($equipement) {
    echo "Test 1 - Update: " . $equipement->getNom() . " (status: " . $equipement->getStatut() . ")\n";
    $equipement->setStatut('En stock');
    $em->flush();
    echo "Updated to: En stock\n";
}

// Test 2: Create new entity
$newEquipement = new \App\Entity\Equipement();
$newEquipement->setNom('Test Audit');
$newEquipement->setTagInventaire('TEST-AUDIT-001');
$newEquipement->setStatut('En stock');
$newEquipement->setModele('Test Model');
$newEquipement->setMarque('Test');
$em->persist($newEquipement);
$em->flush();
echo "Test 2 - Created: ID " . $newEquipement->getId() . "\n";

// Test 3: Delete the test entity
$em->remove($newEquipement);
$em->flush();
echo "Test 3 - Deleted test entity\n";

echo "Done\n";