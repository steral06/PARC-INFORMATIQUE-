<?php

require 'vendor/autoload.php';

$kernel = new \App\Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();

$em = $container->get('doctrine.orm.entity_manager');

echo "EntityManager class: " . get_class($em) . "\n";

// Check EventManager
$eventManager = $em->getEventManager();
echo "EventManager class: " . get_class($eventManager) . "\n";

// Get all registered listeners
$listeners = $eventManager->getListeners();
echo "\nRegistered listeners:\n";
foreach ($listeners as $event => $listenerList) {
    echo "  $event:\n";
    foreach ($listenerList as $listener) {
        echo "    - " . get_class($listener[0]) . "::" . $listener[1] . "\n";
    }
}