<?php

require 'vendor/autoload.php';

$kernel = new \App\Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();

$em = $container->get('doctrine.orm.entity_manager');
$equipement = $em->getRepository(\App\Entity\Equipement::class)->find(1);

if ($equipement) {
    echo "Found equipment: " . $equipement->getNom() . "\n";
    echo "Current status: " . $equipement->getStatut() . "\n";
    
    $equipement->setStatut('En stock');
    $em->flush();
    
    echo "Updated status to: En stock\n";
} else {
    echo "No equipment found with ID 1\n";
}