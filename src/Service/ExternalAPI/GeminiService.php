<?php

namespace App\Service\ExternalAPI;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GeminiService
{
    private string $apiKey;
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;

    public function __construct(
        string $geminiApiKey,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->apiKey = $geminiApiKey;
        $this->httpClient = $httpClient;
        $this->logger = $logger;
    }

    /**
     * Appelle l'API Gemini pour obtenir des recommandations de cultures
     */
    public function getCultureRecommendations(
        float $surface,
        string $typeSol,
        array $meteo,
        float $latitude,
        float $longitude
    ): array {
        try {
            $meteoString = json_encode($meteo, JSON_UNESCAPED_UNICODE);
            
            $prompt = "J'ai une parcelle de {$surface}ha, Sol: {$typeSol}, Climat: {$meteoString}, "
                . "Lat: {$latitude}, Lon: {$longitude}. "
                . "Divise la surface et propose EXACTEMENT 2 cultures différentes en respectant les rotations. "
                . "Réponds STRICTEMENT en JSON : "
                . "{\"culture1\": {\"nom\": \"\", \"type\": \"\", \"surface\": \"\", \"rendement_estime\": \"\", \"justification\": \"\"}, "
                . "\"culture2\": {\"nom\": \"\", \"type\": \"\", \"surface\": \"\", \"rendement_estime\": \"\", \"justification\": \"\"}, "
                . "\"explication_globale\": \"\"}";

            $response = $this->httpClient->request('POST', 
                'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent',
                [
                    'query' => ['key' => $this->apiKey],
                    'headers' => [
                        'Content-Type' => 'application/json',
                    ],
                    'json' => [
                        'contents' => [
                            [
                                'parts' => [
                                    [
                                        'text' => $prompt,
                                    ],
                                ],
                            ],
                        ],
                    ],
                ]
            );

            $data = $response->toArray();

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                $content = $data['candidates'][0]['content']['parts'][0]['text'];
                
                // Extraire le JSON de la réponse
                preg_match('/\{.*\}/s', $content, $matches);
                if (!empty($matches)) {
                    return json_decode($matches[0], true, 512, JSON_THROW_ON_ERROR);
                }
            }

            return ['error' => 'No valid response from Gemini'];
        } catch (\Exception $e) {
            $this->logger->error('Gemini API error: ' . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }
}
