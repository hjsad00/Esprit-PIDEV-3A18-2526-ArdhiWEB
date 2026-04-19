<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiService
{
    private HttpClientInterface $httpClient;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, string $geminiApiKey)
    {
        $this->httpClient = $httpClient;
        $this->apiKey = $geminiApiKey;
    }

    /**
     * Demande recommandation culture à Gemini
     */
    public function recommendCulture(array $context): array
    {
        try {
            $prompt = $this->buildPrompt($context);

            // CHANGEMENT: Utilisation de "gemini-2.5-flash" ou "gemini-flash-latest" car 1.5 n'existe plus
            $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-flash-latest:generateContent?key=' . $this->apiKey;

            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ],
                    'generationConfig' => [
                        'temperature' => 0.7,
                        'maxOutputTokens' => 1024
                    ]
                ]
            ]);

            $data = $response->toArray();
            
            // Loguer pour voir ce que Gemini renvoie en vrai
            error_log('✅ AI API Called using key: ' . substr($this->apiKey, 0, 8) . '...');
            error_log('✅ AI API Response: ' . json_encode($data));

            if (!isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return [
                    'success' => false,
                    'error' => 'Réponse Gemini invalide'
                ];
            }

            $responseText = $data['candidates'][0]['content']['parts'][0]['text'];
            $parsed = $this->parseGeminiResponse($responseText);

            return [
                'success' => true,
                'recommendation' => $parsed
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erreur Gemini: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Construit le prompt pour Gemini
     */
    private function buildPrompt(array $context): string
    {
        $cultureSaisie = $context['culture_saisie'] ?? '';
        $soilType = $context['soil_type'] ?? '';
        $temperature = $context['temperature'] ?? 25;
        $humidity = $context['humidity'] ?? 50;
        $season = $context['season'] ?? '';
        $region = $context['region'] ?? '';

        $prompt = <<<PROMPT
Tu es un expert agricole marocain. Analyse ces conditions et donne une recommandation culturale.

CONDITIONS ACTUELLES:
- Région: $region
- Type de sol: $soilType
- Température: {$temperature}°C
- Humidité: $humidity%
- Saison: $season

CULTURE SAISIE PAR L'UTILISATEUR (si applicable): $cultureSaisie

CONSIGNES:
1. Si culture saisie est appropriée, dis "CULTURE OK: [nom]"
2. Si culture saisie est inappropriée, dis "CULTURE NON OK: [raison]. Meilleures alternatives: [liste]"
3. Si pas de culture saisie, dis "RECOMMANDATION PRINCIPALE: [culture]"

Donne TOUJOURS:
- Une culture principale recommandée
- 3 alternatives possibles
- Une courte justification (2-3 lignes max)
- Les risques/avertissements climatiques
- Conseils pratiques simples

Format ta réponse EXACTEMENT comme ceci:
CULTURE_PRINCIPALE: Tomate
JUSTIFICATION: Température idéale, saison favorable, sol adapté
ALTERNATIVES: Piment, Melon, Concombre
RISQUES: Chaleur modérée, surveiller irrigation
CONSEILS: Arroser régulièrement, paillage recommandé, surveiller humidité

PROMPT;

        return $prompt;
    }

    /**
     * Parse la réponse Gemini
     */
    private function parseGeminiResponse(string $response): array
    {
        $lines = explode("\n", $response);
        $parsed = [
            'principal' => '',
            'justification' => '',
            'alternatives' => [],
            'risks' => [],
            'advices' => [],
            'assessment' => 'OK'
        ];

        foreach ($lines as $line) {
            if (strpos($line, 'CULTURE_PRINCIPALE:') === 0) {
                $parsed['principal'] = trim(str_replace('CULTURE_PRINCIPALE:', '', $line));
            }
            if (strpos($line, 'JUSTIFICATION:') === 0) {
                $parsed['justification'] = trim(str_replace('JUSTIFICATION:', '', $line));
            }
            if (strpos($line, 'ALTERNATIVES:') === 0) {
                $alts = trim(str_replace('ALTERNATIVES:', '', $line));
                $parsed['alternatives'] = array_map('trim', explode(',', $alts));
            }
            if (strpos($line, 'RISQUES:') === 0) {
                $parsed['risks'] = [trim(str_replace('RISQUES:', '', $line))];
            }
            if (strpos($line, 'CONSEILS:') === 0) {
                $parsed['advices'] = [trim(str_replace('CONSEILS:', '', $line))];
            }
            if (strpos($line, 'CULTURE NON OK:') === 0) {
                $parsed['assessment'] = 'NOT_OK';
            }
        }

        return $parsed;
    }
}
