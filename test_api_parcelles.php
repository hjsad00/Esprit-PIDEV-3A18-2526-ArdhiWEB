<?php

// Test API pour les parcelles
use Symfony\Component\HttpClient\HttpClient;

require 'vendor/autoload.php';

$client = HttpClient::create();

echo "Test API Parcelles\n";
echo "==================\n\n";

// Test 1: Get parcelles
echo "1. GET /farmer/irrigation/api/parcelles\n";
try {
    $response = $client->request('GET', 'http://localhost:8000/farmer/irrigation/api/parcelles');
    echo "Status: " . $response->getStatusCode() . "\n";
    echo "Response: " . json_encode($response->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n\n";
}

// Si on obtient des parcelles, on teste le détail
echo "Note: Assurez-vous d'être authentifié pour accéder à ces endpoints.\n";
?>
