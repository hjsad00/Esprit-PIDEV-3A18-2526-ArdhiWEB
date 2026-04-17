<?php

// Test script for IrrigationService API
// Run: curl -X POST http://localhost:8000/api/irrigation/calculate \
//      -H "Content-Type: application/json" \
//      -d '{"latitude":36.8,"longitude":10.2,"culture":"tomate","surface":5}'

require 'vendor/autoload.php';

$testData = [
    'latitude' => 36.8,
    'longitude' => 10.2,
    'culture' => 'tomate',
    'surface' => 5,
];

echo "Test Irrigation API\n";
echo "===================\n\n";
echo "Payload: " . json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'http://localhost:8000/api/irrigation/calculate',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode($testData),
]);

$response = curl_exec($curl);
$httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

echo "Response Code: {$httpCode}\n";
echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
