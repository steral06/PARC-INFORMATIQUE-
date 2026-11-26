<?php

namespace App\DependencyInjection\Compiler;

use App\EventListener\AuditDoctrineSubscriber;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class AuditDoctrineSubscriberPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $eventManagerId = 'doctrine.dbal.default_connection.event_manager';

        if (!$container->hasDefinition($eventManagerId)) {
            return;
        }

        // Check if our subscriber service exists
        if (!$container->hasDefinition(AuditDoctrineSubscriber::class)) {
            return;
        }

        // Add a method call to register the event subscriber on the EventManager
        $eventManagerDef = $container->findDefinition($eventManagerId);
        $eventManagerDef->addMethodCall('addEventSubscriber', [new Reference(AuditDoctrineSubscriber::class)]);
    }
}