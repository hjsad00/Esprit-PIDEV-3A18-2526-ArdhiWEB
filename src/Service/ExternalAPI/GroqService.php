<?php

namespace App\Service\ExternalAPI;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GroqService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        string $groqApiKey,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->apiKey = $groqApiKey;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Appelle l'API Groq pour obtenir des recommandations de sol et d'irrigation
     */
    public function getFieldRecommendations(float $latitude, float $longitude, string $localisation): array
    {
        try {
            $prompt = "Tu es un expert en agriculture à {$localisation} (lat: {$latitude}, lon: {$longitude}). "
                . "Recommande EXACTEMENT UNE seule option parmi : "
                . "Type de sol: Argileux, Sableux, Limoneux, Calcaire, Argilo-sableux, Argilo-limoneux, Tourbeux. "
                . "Système d'irrigation: Goutte-à-goutte, Aspersion, Gravitaire, Pivot, Micro-aspersion, Pluvial. "
                . "Réponds STRICTEMENT en JSON valide (pas d'autres caractères, juste le JSON) : "
                . "{\"type_sol\": \"valeur_exacte\", \"systeme_irrigation\": \"valeur_exacte\", \"explication\": \"Justification courte\"}";

            $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => [
                        [
                            'role' => 'user',
                            'content' => $prompt,
                        ],
                    ],
                    'temperature' => 0.3,
                    'max_tokens' => 500,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];
                
                // Extraire le JSON de la réponse
                preg_match('/\{.*\}/s', $content, $matches);
                if (!empty($matches)) {
                    $result = json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
                    
                    // Valider que les valeurs retournées sont dans les options autorisées
                    $typeSolOptions = ['Argileux', 'Sableux', 'Limoneux', 'Calcaire', 'Argilo-sableux', 'Argilo-limoneux', 'Tourbeux'];
                    $irrigationOptions = ['Goutte-à-goutte', 'Aspersion', 'Gravitaire', 'Pivot', 'Micro-aspersion', 'Pluvial'];
                    
                    if (!in_array($result['type_sol'] ?? '', $typeSolOptions)) {
                        $result['type_sol'] = $typeSolOptions[0]; // Défaut
                    }
                    if (!in_array($result['systeme_irrigation'] ?? '', $irrigationOptions)) {
                        $result['systeme_irrigation'] = $irrigationOptions[0]; // Défaut
                    }
                    
                    return $result;
                }
            }

            return ['error' => 'No valid response from Groq'];
        } catch (\Exception $e) {
            $this->logger->error('Groq API error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
