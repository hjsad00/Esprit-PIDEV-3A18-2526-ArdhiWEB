<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;

try {
    $rc = new ReflectionClass(Builder::class);
    echo "Class: " . Builder::class . "\n";
    echo "File: " . $rc->getFileName() . "\n";
    echo "Methods: \n";
    foreach ($rc->getMethods(ReflectionMethod::IS_STATIC) as $method) {
        echo "  - static " . $method->getName() . "\n";
    }
} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
