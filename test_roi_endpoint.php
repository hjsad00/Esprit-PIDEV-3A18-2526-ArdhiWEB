<?php
/**
 * Test API ROI Endpoint
 * Teste la connexion Symfony → Python
 */

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "http://127.0.0.1:8000/farmer/roi/analyze");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    'surface' => 5,
    'rendement' => 50,
    'culture' => 'Tomate',
    'cout_semences' => 1000,
    'cout_engrais' => 2000,
    'cout_main_oeuvre' => 1500,
    'cout_irrigation' => 500,
    'autres_couts' => 300,
    'jours_canicule' => 4,
    'jours_pluie' => 2,
    'jours_gel' => 0,
    'prix_vente' => 5,
]));

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "📤 Testing /farmer/roi/analyze endpoint...\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);

curl_close($ch);

if ($error) {
    echo "❌ Erreur CURL: $error\n";
} else {
    echo "HTTP Status: $httpCode\n\n";
    
    if ($httpCode === 200 || $httpCode === 404) {
        $data = json_decode($response, true);
        if ($data) {
            echo "✅ Response JSON Valid!\n\n";
            echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            echo "Response:\n$response\n";
        }
    } else {
        echo "Response:\n$response\n";
    }
}
