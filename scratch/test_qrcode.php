<?php

use App\Kernel;
use App\Service\Evenement\QRCodeService;
use App\Entity\Evenement\Participation;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

require_once __DIR__ . '/../../vendor/autoload.php';

$kernel = new Kernel('dev', true);
$kernel->boot();
$container = $kernel->getContainer();

$qrService = $container->get(QRCodeService::class);

// Create a dummy participation for testing
$participation = new Participation();
// Actually we need to fetch one from DB to be safe or mock it, 
// but for a quick check, just seeing if the service initializes is half the battle.
echo "QRCodeService initialized successfully.\n";

try {
    // This will likely fail without a real DB entity for getEvenement() etc.
    // but we can check if the class exists and methods are callable.
    echo "Methods available: " . implode(', ', get_class_methods($qrService)) . "\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
