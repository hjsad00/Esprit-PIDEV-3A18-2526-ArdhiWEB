<?php
// Lister les modèles Gemini disponibles
$key = 'AIzaSyBbC5qXV-ByBrEkNy6lf39FeEtJZji8cec';
$ch = curl_init('https://generativelanguage.googleapis.com/v1beta/models?key=' . $key);
curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
$r = json_decode(curl_exec($ch), true);
curl_close($ch);
foreach (($r['models'] ?? []) as $m) {
    if (strpos($m['name'], 'gemini') !== false) {
        $methods = implode(', ', $m['supportedGenerationMethods'] ?? []);
        echo $m['name'] . ' -> ' . $methods . PHP_EOL;
    }
}
