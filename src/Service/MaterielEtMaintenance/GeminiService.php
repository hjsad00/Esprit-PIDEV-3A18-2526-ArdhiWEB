<?php

namespace App\Service\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GeminiService
{
    private const API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent';
    
    private HttpClientInterface $httpClient;
    private LoggerInterface $logger;
    private string $apiKey;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger, string $geminiApiKey)
    {
        $this->httpClient = $httpClient;
        $this->logger = $logger;
        $this->apiKey = trim($geminiApiKey);
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
            
            RÉPONDS EXCLUSIVEMENT AU FORMAT JSON SUIVANT (sans texte avant ou après, sans balises ```json) :
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
            $response = $this->httpClient->request('POST', self::API_URL . '?key=' . $this->apiKey, [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt]
                            ]
                        ]
                    ]
                ],
                'timeout' => 30,
            ]);

            $result = $response->toArray();
            $textResponse = $result['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!$textResponse) {
                throw new \Exception("Réponse vide de Gemini.");
            }

            // Nettoyage de la réponse au cas où le modèle inclurait des balises markdown ```json
            $textResponse = preg_replace('/^```json|```$/m', '', $textResponse);
            $textResponse = trim($textResponse);

            $decoded = json_decode($textResponse, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                // Tentative désespérée : extraire le premier bloc JSON trouvé
                if (preg_match('/\{.*\}/s', $textResponse, $matches)) {
                    $decoded = json_decode($matches[0], true);
                }
            }

            if (!$decoded) {
                throw new \Exception("Erreur de décodage JSON : " . json_last_error_msg());
            }

            return $decoded;

        } catch (\Exception $e) {
            $this->logger->error("Gemini API Error: " . $e->getMessage());
            return [
                'error' => true,
                'message' => "Impossible de générer la prédiction pour le moment. " . $e->getMessage()
            ];
        }
    }
}
