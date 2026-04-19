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
            $prompt = "Je suis à {$localisation} (lat: {$latitude}, lon: {$longitude}). "
                . "Recommande le type de sol, le système d'irrigation, la surface et donne une explication. "
                . "Réponds STRICTEMENT en JSON : "
                . "{\"type_sol\": \"\", \"systeme_irrigation\": \"\", \"surface_recommandee\": \"\", \"explication\": \"\"}";

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
                    'temperature' => 0.7,
                    'max_tokens' => 1000,
                ],
            ]);

            $data = $response->toArray();

            if (isset($data['choices'][0]['message']['content'])) {
                $content = $data['choices'][0]['message']['content'];
                
                // Extraire le JSON de la réponse
                preg_match('/\{.*\}/s', $content, $matches);
                if (!empty($matches)) {
                    return json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
                }
            }

            return ['error' => 'No valid response from Groq'];
        } catch (\Exception $e) {
            $this->logger->error('Groq API error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
