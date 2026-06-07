<?php

namespace App\Service\UserAndDiag;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Psr\Log\LoggerInterface;

class GroqService
{
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
                    'Authorization' => 'Bearer ' . ($_ENV['GROQ_API_KEY'] ?? ''),
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

    /**
     * Generates a day-by-day treatment task list based on the disease name.
     */
    public function generateTreatmentPlan(string $diseaseName): string
    {
        $prompt = "Génère un plan de traitement agricole complet et détaillé pour la maladie : " . $diseaseName . ". " .
            "Le plan doit s'étendre sur 10 jours. " .
            "Génère au moins 3 à 5 tâches différentes réparties sur ces 10 jours. " .
            "Réponds UNIQUEMENT avec une liste de tâches au format : 'JOUR|DESCRIPTION'. " .
            "Exemple: \n" .
            "1|Isoler la plante et couper les feuilles infectées.\n" .
            "3|Appliquer un traitement fongicide ciblé.\n" .
            "7|Vérifier l'état visuel (sans scanner).\n" .
            "Ne mets pas de texte avant ou après. Une tâche par ligne.\n" .
            "INTERDICTION STRICTE : Ne jamais utiliser de blocs de code Markdown (```). Réponds en texte brut seulement.\n" .
            "INTERDICTION STRICTE : Ne jamais proposer de tâche demandant de scanner, prendre une photo, ou réévaluer la plante via l'application. Ce bouton existe déjà.";

        // In PHP with Symfony's HttpClient, we don't need to manually build the JSON string 
        // with escaped quotes. We just build a clean PHP array, and Symfony handles the rest!
        $jsonPayload = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ]
        ];

        return $this->sendRequest($jsonPayload);
    }
    /**
     * Etape 1: Vérifie si l'image téléchargée correspond bien à la plante/maladie traitée.
     */
    public function checkConsistency(UploadedFile $newImage, string $diseaseName): string
    {
        $mimeType = $newImage->getMimeType() ?: 'image/jpeg';
        $imageBase64 = base64_encode(file_get_contents($newImage->getPathname()));

        $prompt = "Voici une image d'une plante. Le protocole actuel traite la maladie : " . $diseaseName . ". " .
            "Est-ce que cette image correspond bien à cette plante ou ce fruit ou ce légume et montre des signes liés à cette maladie (même en voie de guérison ou de détérioration) ? " .
            "Réponds STRICTEMENT par 'MATCH' si c'est cohérent, ou 'MISMATCH|Raison détaillée' si c'est une toute autre plante ou totalement incohérent.";

        $jsonPayload = [
            'model' => 'meta-llama/llama-4-scout-17b-16e-instruct', // Or your preferred vision model
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]]
                    ]
                ]
            ]
        ];

        return $this->sendRequest($jsonPayload);
    }

    /**
     * Etape 2: Compare l'ancienne image et la nouvelle pour évaluer la guérison.
     */
    public function analyzeRecovery(string $baselineUrl, UploadedFile $newImage, string $diseaseName): string
    {
        $mimeType = $newImage->getMimeType() ?: 'image/jpeg';
        $imageBase64 = base64_encode(file_get_contents($newImage->getPathname()));

        $prompt = "Tu es un expert agronome. Tu dois évaluer l'évolution de la maladie : " . $diseaseName . ". " .
            "La première image est l'état initial. La deuxième image est l'état ACTUEL après traitement. " .
            "Évalue la progression de la maladie. " .
            "Réponds STRICTEMENT avec un seul de ces mots-clés suivi d'un '|' et d'une brève explication : " .
            "HEALED (totalement guérie), RECOVERING (en nette amélioration), UNCHANGED (stationnaire), WORSENING (aggravation). " .
            "Exemple: RECOVERING|Les taches brunes ont séché et ne s'étendent plus.";

        $jsonPayload = [
            'model' => 'meta-llama/llama-4-scout-17b-16e-instruct',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => [
                        ['type' => 'text', 'text' => $prompt],
                        ['type' => 'image_url', 'image_url' => ['url' => $baselineUrl]], // Image 1: Baseline
                        ['type' => 'image_url', 'image_url' => ['url' => "data:{$mimeType};base64,{$imageBase64}"]] // Image 2: New
                    ]
                ]
            ]
        ];

        return $this->sendRequest($jsonPayload);
    }

    /**
     * Etape 3: Génère un nouveau plan si la situation s'aggrave.
     */
    public function generateUpdatedPlan(string $baselineUrl, UploadedFile $newImage, string $diseaseName): string
    {
        // Reuses the text-only generateTreatmentPlan method logic, but informs the AI it's a worsening case
        $prompt = "La maladie " . $diseaseName . " s'est aggravée malgré un premier traitement. " .
            "Génère un NOUVEAU plan de traitement de crise sur 10 jours avec des méthodes plus radicales ou alternatives. " .
            "Génère 3 à 5 tâches réparties sur ces 10 jours. " .
            "Réponds UNIQUEMENT avec une liste au format : 'JOUR|DESCRIPTION'. Une tâche par ligne. Aucun texte avant ou après. " .
            "Exemple: 1|Appliquer un fongicide systémique puissant.\n3|Tailler agressivement les parties nécrosées.";

        $jsonPayload = [
            'model' => 'llama-3.3-70b-versatile',
            'messages' => [['role' => 'user', 'content' => $prompt]]
        ];

        return $this->sendRequest($jsonPayload);
    }
    /**
     * Analyses multiple farm photos and returns a structured JSON report.
     */
    public function analyzeFarmHealth(array $imagePaths, array $scanDetails): string
    {
        $prompt = "Tu es un expert agronome spécialisé dans la prévention agricole. " .
            "Contexte du champ : Culture: {$scanDetails['crop']}, Date de plantation: {$scanDetails['plantingDate']}, Stade: {$scanDetails['stage']}, Préoccupations: {$scanDetails['concerns']}. " .
            "INSTRUCTIONS : \n" .
            "1. Analyse ces images pour identifier les RISQUES POTENTIELS.\n" .
            "2. Cherche des signes de : ravageurs potentiels, conditions favorables aux maladies, carences nutritives, problèmes de pollinisation, dégradation du sol.\n" .
            "3. En plus de lister les vulnérabilités, évalue la santé globale (0-100), la biodiversité (0-100), et fournis des plans de prévention structurés.\n" .
            "Tu DOIS répondre EXCLUSIVEMENT avec un objet JSON valide, sans aucun texte avant ou après, en respectant cette structure exacte :\n" .
            "{\n" .
            "  \"health_score\": (entier de 0 à 100),\n" .
            "  \"biodiversity_score\": (entier de 0 à 100),\n" .
            "  \"llava_analysis\": \"Résumé textuel de ton analyse\",\n" .
            "  \"vulnerabilities\": [\n" .
            "    // GÉNÈRE AUTANT D'OBJETS QUE NÉCESSAIRE SI PLUSIEURS MENACES SONT DÉTECTÉES\n" .
            "    {\"type\": \"PEST_OUTBREAK_RISK|DISEASE_RISK|NUTRIENT_DEFICIENCY|LOW_POLLINATION|SOIL_DEGRADATION\", \"threat\": \"Nom de la menace\", \"severity\": \"MEDIUM\", \"description\": \"...\", \"risk_score\": 0.4, \"timeframe_days\": 14, \"yield_loss\": 15, \"cost\": 500}\n" .
            "  ],\n" .
            "  \"prevention_plans\": [\n" .
            "    {\n" .
            "      \"title\": \"Plan d'action\",\n" .
            "      \"timeline_days\": 14,\n" .
            "      \"impact_level\": \"HIGH\",\n" .
            "      \"tasks\": [\n" .
            "        {\"day\": 1, \"description\": \"Inspecter les feuilles\"},\n" .
            "        {\"day\": 3, \"description\": \"Appliquer traitement préventif\"}\n" .
            "      ]\n" .
            "    }\n" .
            "  ]\n" .
            "}";

        $content = [['type' => 'text', 'text' => $prompt]];

        // Attach images to the prompt (Max 5 for vision model limit)
        $limitedPaths = array_slice($imagePaths, 0, 5);
        foreach ($limitedPaths as $path) {
            if (file_exists($path)) {
                $base64 = base64_encode(file_get_contents($path));
                // Assuming jpeg for simplicity, though Groq usually auto-detects
                $content[] = ['type' => 'image_url', 'image_url' => ['url' => "data:image/jpeg;base64,{$base64}"]];
            }
        }

        $jsonPayload = [
            'model' => 'meta-llama/llama-4-scout-17b-16e-instruct', // The multi-modal model
            'messages' => [['role' => 'user', 'content' => $content]],
            // Note: Groq vision models do not currently support 'response_format' => ['type' => 'json_object']
            // So we rely entirely on the strict prompt instructions.
        ];

        return $this->sendRequest($jsonPayload);
    }
}
