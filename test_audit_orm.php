<?php

require 'vendor/autoload.php';

use App\Kernel;

$kernel = new Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();

$em = $container->get('doctrine.orm.entity_manager');
$equipement = $em->getRepository(\App\Entity\Equipement::class)->find(1);

if ($equipement) {
    echo "Found equipment: " . $equipement->getNom() . "\n";
    echo "Current status: " . $equipement->getStatut() . "\n";
    
    // Change to a different status to trigger update
    $equipement->setStatut('En service');
    $em->flush();
    
    echo "Updated status to: En service\n";
} else {
    echo "No equipment found with ID 1\n";
}