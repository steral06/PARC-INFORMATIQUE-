<?php

$content = file_get_contents('var/cache/prod/App_KernelProdContainer.php');

if (strpos($content, 'AuditDoctrineSubscriber') !== false) {
    echo "Found AuditDoctrineSubscriber in container\n";
    
    // Find relevant lines
    $lines = explode("\n", $content);
    foreach ($lines as $i => $line) {
        if (strpos($line, 'AuditDoctrineSubscriber') !== false) {
            echo "Line " . ($i+1) . ": " . trim($line) . "\n";
        }
    }
} else {
    echo "NOT FOUND in container\n";
}