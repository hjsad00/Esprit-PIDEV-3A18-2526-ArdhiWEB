<?php

namespace App\Service\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GroqPredictionService
{
    private const API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $groqApiKey;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        // La clé Groq utilisée pour le chatbot
        $this->groqApiKey = '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';
    }

    public function generatePrediction(Materiel $materiel): array
    {
        $historyText = "";
        foreach ($materiel->getMaintenances() as $m) {
            $historyText .= sprintf("- %s: %s (Statut: %s)\n", 
                $m->getDateMaintenance() ? $m->getDateMaintenance()->format('d/m/Y') : 'N/A',
                $m->getDescription() ?: 'Entretien standard',
                $m->getStatutMaintenance()
            );
        }

        $prompt = sprintf(
            "Tu es un ingénieur expert en maintenance prédictive pour le matériel agricole 'Ardhi'.
            Analyse les données suivantes et fournis un diagnostic PRÉCIS et PROFESSIONNEL.
            
            DONNÉES DU MATÉRIEL :
            - Nom : %s
            - Type : %s
            - État actuel : %s
            - Heures d'utilisation : %d h
            - Seuil de maintenance habituel : %d h
            - Heures depuis la dernière maintenance : %d h
            
            HISTORIQUE DES INTERVENTIONS :
            %s
            
            CONSIGNES :
            1. Évalue le NIVEAU DE RISQUE (Faible, Modéré, Élevé).
            2. Donne un CONSEIL TECHNIQUE spécifique pour ce type de machine.
            3. Estime le temps restant avant la prochaine panne critique.
            
            RÉPONDS EXCLUSIVEMENT AU FORMAT JSON SUIVANT (sans texte avant ou après, sans balises de code) :
            {
                \"risque\": \"Faible|Modéré|Élevé\",
                \"score_risque\": 0-100,
                \"analyse\": \"Ton analyse détaillée ici...\",
                \"conseils\": [\"Conseil 1\", \"Conseil 2\"],
                \"prochaine_etape\": \"Action recommandée immédiate\"
            }",
            $materiel->getNom(),
            $materiel->getType(),
            $materiel->getEtat(),
            $materiel->getHeuresUtilisation(),
            $materiel->getSeuilMaintenanceHeures(),
            $materiel->getHeuresUtilisation() - $materiel->getDerniereMaintenanceHeures(),
            $historyText ?: "Aucun historique disponible."
        );

        try {
            $response = $this->httpClient->request('POST', self::API_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->groqApiKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.3-70b-versatile',
                    'messages' => [
                        ['role' => 'user', 'content' => $prompt]
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => 1024,
                    'response_format' => ['type' => 'json_object']
                ],
                'timeout' => 30,
            ]);

            $result = $response->toArray();
            $textResponse = $result['choices'][0]['message']['content'] ?? null;

            if (!$textResponse) {
                throw new \Exception("Réponse vide ou malformée de Groq (content manquant).");
            }

            $decoded = json_decode($textResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
                throw new \Exception("Erreur de décodage JSON de la réponse Groq.");
            }

            return $decoded;

        } catch (\Exception $e) {
            $this->logger->error("Groq Prediction Error: " . $e->getMessage());
            return [
                'error' => true,
                'message' => "Impossible de générer la prédiction. " . $e->getMessage()
            ];
        }
    }
}
