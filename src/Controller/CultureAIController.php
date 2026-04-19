<?php

namespace App\Controller;

use App\Service\CultureAdvisorService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class CultureAIController extends AbstractController
{
    private CultureAdvisorService $advisorService;

    public function __construct(CultureAdvisorService $advisorService)
    {
        $this->advisorService = $advisorService;
    }

    /**
     * Analyse parcelle et retourne recommandation IA
     */
    #[Route('/culture/analyze', name: 'api_culture_analyze', methods: ['POST'])]
    public function analyzeCulture(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            $parcelleId = $data['parcelle_id'] ?? null;
            $season = $data['season'] ?? 'printemps';
            $cultureSaisie = $data['culture'] ?? null;

            if (!$parcelleId) {
                return $this->json(['error' => 'parcelle_id requis'], 400);
            }

            $analysis = $this->advisorService->analyzeParcelleForCulture(
                $parcelleId,
                $season,
                $cultureSaisie
            );

            if (!$analysis['success']) {
                return $this->json(['error' => $analysis['error']], 400);
            }

            // Sauvegarder la suggestion
            $this->advisorService->saveSuggestion($parcelleId, $analysis, false);

            return $this->json($analysis);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Valide acceptation recommandation
     */
    #[Route('/culture/accept-recommendation', name: 'api_culture_accept', methods: ['POST'])]
    public function acceptRecommendation(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $parcelleId = $data['parcelle_id'] ?? null;

            if (!$parcelleId) {
                return $this->json(['error' => 'parcelle_id requis'], 400);
            }

            // Marquer acceptation en BD
            $conn = $this->getDoctrine()->getConnection();
            $sql = "UPDATE ai_suggestions SET accepted = 1 WHERE parcelle_id = :parcelle_id ORDER BY created_at DESC LIMIT 1";
            $conn->executeStatement($sql, ['parcelle_id' => $parcelleId]);

            return $this->json(['success' => true, 'message' => 'Recommandation acceptée']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
