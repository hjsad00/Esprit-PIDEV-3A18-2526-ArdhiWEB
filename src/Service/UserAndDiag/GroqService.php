<?php

namespace App\Service\UserAndDiag;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;

class GroqService
{
    private const API_KEY = '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';
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';

    private HttpClientInterface $client;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Send request to Groq API.
     */
    private function sendRequest(array $jsonPayload): string
    {
        try {
            $response = $this->client->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . self::API_KEY,
                    'Content-Type' => 'application/json',
                ],
                'json' => $jsonPayload,
                'timeout' => 90, // Match the 90s read timeout from Java
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                return "ERREUR_API_{$statusCode}: " . $response->getContent(false);
            }

            $content = $response->toArray();
            if (isset($content['choices'][0]['message']['content'])) {
                // Return exactly the content string provided by LlaMa
                return trim($content['choices'][0]['message']['content']);
            }

            return "ERREUR_FORMAT";
        } catch (\Exception $e) {
            $this->logger->error("GroqService error: " . $e->getMessage());
            return "ERREUR_TECHNIQUE: " . $e->getMessage();
        }
    }

    /**
     * Replicates analyserImage from Java.
     * Takes an UploadedFile, converts to Base64, and prompts the vision model.
     * Note: If the user's specific model string fails in production, you evaluate fallback to a known vision model like 'llama-3.2-11b-vision-preview'.
     */
    public function analyserImage(UploadedFile $imageFile): string
    {
        try {
            $fileContent = file_get_contents($imageFile->getPathname());
            if ($fileContent === false) {
                throw new \Exception("Could not read image file.");
            }

            $mimeType = $imageFile->getMimeType() ?: 'image/jpeg';
            $imageBase64 = base64_encode($fileContent);

            $promptText = "Tu es un expert agronome spécialisé dans l'agriculture. " .
                "Analyse cette image de plante. " .
                "INSTRUCTIONS PRIORITAIRES : " .
                "1. Identifie précisément la plante et la maladie. " .
                "2. Confiance (0-100) : Calcule un score de confiance réaliste basé sur la clarté de l'image et l'aisance du diagnostic. Évite de donner systématiquement 80. Utilise des valeurs variées (ex: 76, 84, 91). " .
                "3. Pour le nom du produit, utilise des marques disponibles en Tunisie." .
                "4. Type de traitement: FONGICIDE, HERBICIDE, INSECTICIDE, BACTERICIDE, NEMATICIDE, VIRUCIDE, NUTRIMENT, REGULATEUR_CROISSANCE, ou AUTRE. " .
                "5. Description : Sois précis sur le dosage et le moment (ex: à l'aube, éviter le vent). " .
                "6. Niveau de gravité: CRITICAL (maladie grave, action urgente), MEDIUM (modéré, traitement conseillé), LOW (léger ou plante saine). " .
                "FORMAT DE RÉPONSE (Strictement une seule ligne) : " .
                "PLANTE|MALADIE|CONFIANCE|NOM_PRODUIT|TYPE_TRAITEMENT|DESCRIPTION_DOSAGE_ET_APPLICATION|SEVERITY";

            $jsonPayload = [
                'model' => 'meta-llama/llama-4-scout-17b-16e-instruct',
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'text',
                                'text' => $promptText
                            ],
                            [
                                'type' => 'image_url',
                                'image_url' => [
                                    'url' => "data:{$mimeType};base64,{$imageBase64}"
                                ]
                            ]
                        ]
                    ]
                ]
            ];

            return $this->sendRequest($jsonPayload);

        } catch (\Exception $e) {
            $this->logger->error("GroqService image analysis error: " . $e->getMessage());
            return "ERREUR_TECHNIQUE: " . $e->getMessage();
        }
    }
}
