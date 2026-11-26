<?php

require 'vendor/autoload.php';

$kernel = new App\Kernel('prod', false);
$kernel->boot();
$c = $kernel->getContainer();

echo 'Has service: ' . ($c->has('App\EventListener\AuditDoctrineSubscriber') ? 'yes' : 'no') . PHP_EOL;
if ($c->has('App\EventListener\AuditDoctrineSubscriber')) {
    $s = $c->get('App\EventListener\AuditDoctrineSubscriber');
    echo 'Service: ' . get_class($s) . PHP_EOL;
}