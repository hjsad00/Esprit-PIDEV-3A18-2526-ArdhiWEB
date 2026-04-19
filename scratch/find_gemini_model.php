<?php
// Tester plusieurs modèles jusqu'à en trouver un qui marche
$apiKey = 'AIzaSyBbC5qXV-ByBrEkNy6lf39FeEtJZji8cec';
$models = [
    'gemini-2.5-flash-lite',
    'gemini-2.5-flash',
    'gemini-2.0-flash-lite',
    'gemini-flash-latest',
    'gemini-flash-lite-latest',
];

foreach ($models as $model) {
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
    $payload = json_encode([
        'contents' => [['parts' => [['text' => 'Bonjour, réponds en 1 mot : OK']]]],
        'generationConfig' => ['maxOutputTokens' => 10]
    ]);
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payload,
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true, CURLOPT_TIMEOUT=>8]);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $data = json_decode($response, true);
    $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? ('ERR '.$code.': '.($data['error']['message']??''));
    echo "[$model] HTTP $code => $text\n";
    if ($code === 200) {
        echo "\n✅ Modèle fonctionnel : $model\n";
        break;
    }
    sleep(1);
}
