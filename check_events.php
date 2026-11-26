<?php

require 'vendor/autoload.php';

echo "Doctrine\ORM\Events constants:\n";
$r = new ReflectionClass(\Doctrine\ORM\Events::class);
foreach ($r->getConstants() as $name => $value) {
    echo "  $name = $value\n";
}