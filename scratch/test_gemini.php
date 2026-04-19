<?php
// Test rapide de l'API Gemini
$apiKey = 'AIzaSyBbC5qXV-ByBrEkNy6lf39FeEtJZji8cec';
$url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=' . $apiKey;

$payload = json_encode([
    'contents' => [[
        'parts' => [['text' => 'Réponds en français en 1 phrase : Qui peut irriguer les oliviers ?']]
    ]],
    'generationConfig' => ['maxOutputTokens' => 100]
]);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$data = json_decode($response, true);
$text = $data['candidates'][0]['content']['parts'][0]['text'] ?? ('ERREUR: ' . json_encode($data));

echo "HTTP: $httpCode\n";
echo "Réponse Gemini: $text\n";
