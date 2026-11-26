<?php

require 'vendor/autoload.php';

$kernel = new \App\Kernel('prod', false);
$kernel->boot();
$container = $kernel->getContainer();

// Get the subscriber
$subscriber = $container->get('App\EventListener\AuditDoctrineSubscriber');
echo "Subscriber: " . get_class($subscriber) . "\n";

// Get the EntityManager
$em = $container->get('doctrine.orm.entity_manager');
echo "EntityManager: " . get_class($em) . "\n";

// Get the EventManager
$eventManager = $em->getEventManager();
echo "EventManager: " . get_class($eventManager) . "\n";

// Check if subscriber is registered
$listeners = $eventManager->getListeners();
echo "\nRegistered listeners:\n";
foreach ($listeners as $event => $listenerList) {
    echo "  $event:\n";
    foreach ($listenerList as $listener) {
        echo "    - " . get_class($listener[0]) . "::" . $listener[1] . "\n";
    }
}