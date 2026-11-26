<?php

require 'vendor/autoload.php';

$r = new ReflectionClass('App\EventListener\AuditDoctrineSubscriber');
echo 'Implements EventSubscriber: ' . ($r->implementsInterface('Doctrine\Common\EventSubscriber') ? 'YES' : 'NO') . PHP_EOL;
echo 'Methods: ' . implode(', ', array_map(fn($m) => $m->getName(), $r->getMethods(ReflectionMethod::IS_PUBLIC))) . PHP_EOL;