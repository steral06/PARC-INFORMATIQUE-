<?php

require 'vendor/autoload.php';

$container = require 'var/cache/prod/App_KernelProdContainer.php';

echo 'Has service: ' . ($container->has('App\EventListener\AuditDoctrineSubscriber') ? 'yes' : 'no') . PHP_EOL;