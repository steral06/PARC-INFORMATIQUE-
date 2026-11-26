<?php

require 'vendor/autoload.php';

echo \Doctrine\Common\EventSubscriber::class . PHP_EOL;
$r = new ReflectionClass(\Doctrine\Common\EventSubscriber::class);
echo 'Methods: ' . implode(', ', array_map(fn($m) => $m->getName(), $r->getMethods())) . PHP_EOL;