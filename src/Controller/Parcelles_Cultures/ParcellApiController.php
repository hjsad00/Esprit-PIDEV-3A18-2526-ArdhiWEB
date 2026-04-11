<?php

namespace App\Controller\Parcelles_Cultures;

use App\Service\ExternalAPI\GroqService;
use App\Service\ExternalAPI\GeminiService;
use App\Service\ExternalAPI\WeatherService;
use App\Repository\Parcelles_Cultures\ParcelleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/parcelles', name: 'api_parcelle_', format: 'json')]
class ParcellApiController extends AbstractController
{
    public function __construct(
        private GroqService $groqService,
        private GeminiService $geminiService,
        private WeatherService $weatherService,
        private ParcelleRepository $parcelleRepository,
        private HttpClientInterface $httpClient
    ) {
    }

    /**
     * Endpoint de test (DEBUG)
     * Accessible sans authentification
     */
    #[Route('/test', name: 'test', methods: ['GET'])]
    public function test(): JsonResponse
    {
        $now = (new \DateTime())->format('Y-m-d H:i:s');
        return $this->json([
            'status' => 'ok',
            'message' => 'API Parcelles est en ligne',
            'timestamp' => $now,
        ]);
    }

    /**
     * Obtenir les recommandations IA pour le type de sol et l'irrigation (Groq)
     * Accessible sans authentification (pour le formulaire de création)
     */
    #[Route('/field-recommendations', name: 'field_recommendations', methods: ['POST'])]
    public function fieldRecommendations(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['latitude'], $data['longitude'], $data['localisation'])) {
                return $this->json(['error' => 'Données manquantes: latitude, longitude, localisation requis'], 400);
            }

            $recommendations = $this->groqService->getFieldRecommendations(
                (float) $data['latitude'],
                (float) $data['longitude'],
                $data['localisation']
            );

            return $this->json($recommendations);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur serveur: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtenir les recommandations de cultures (Gemini)
     * Requiert authentification
     */
    #[Route('/culture-recommendations', name: 'culture_recommendations', methods: ['POST'])]
    #[IsGranted('ROLE_AGRICULTEUR')]
    public function cultureRecommendations(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['parcelleId'])) {
            return $this->json(['error' => 'ID de parcelle manquant'], 400);
        }

        $parcelle = $this->parcelleRepository->find($data['parcelleId']);
        if (!$parcelle) {
            return $this->json(['error' => 'Parcelle non trouvée'], 404);
        }

        // Vérifier l'accès
        $this->denyAccessUnlessGranted('view', $parcelle);

        // Récupérer les données météo
        $weatherData = $this->weatherService->getWeatherData(
            (float) $parcelle->getLatitude(),
            (float) $parcelle->getLongitude()
        );

        if (isset($weatherData['error'])) {
            return $this->json(['error' => 'Erreur météo: ' . $weatherData['error']], 500);
        }

        $recommendations = $this->geminiService->getCultureRecommendations(
            (float) $parcelle->getSurface(),
            $parcelle->getTypeSol(),
            $weatherData['current'] ?? [],
            (float) $parcelle->getLatitude(),
            (float) $parcelle->getLongitude()
        );

        return $this->json($recommendations);
    }

    /**
     * Obtenir les données météo d'une parcelle
     */
    #[Route('/{id}/weather', name: 'weather', methods: ['GET'])]
    public function weather(int $id): JsonResponse
    {
        $parcelle = $this->parcelleRepository->find($id);
        if (!$parcelle) {
            return $this->json(['error' => 'Parcelle non trouvée'], 404);
        }

        // Vérifier l'accès
        $this->denyAccessUnlessGranted('view', $parcelle);

        if (!$parcelle->getLatitude() || !$parcelle->getLongitude()) {
            return $this->json(['error' => 'Localisation manquante'], 400);
        }

        $weatherData = $this->weatherService->getWeatherData(
            (float) $parcelle->getLatitude(),
            (float) $parcelle->getLongitude()
        );

        if (isset($weatherData['error'])) {
            return $this->json(['error' => 'Erreur météo: ' . $weatherData['error']], 500);
        }

        $alerts = $this->weatherService->analyzeWeather($weatherData);

        return $this->json([
            'weather' => $weatherData,
            'alerts' => $alerts,
        ]);
    }

    /**
     * Obtenir les coordonnées depuis Nominatim (reverse geocoding)
     */
    #[Route('/nominatim-reverse', name: 'nominatim_reverse', methods: ['POST'])]
    public function nominatimReverse(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        
        if (!isset($data['latitude'], $data['longitude'])) {
            return $this->json(['error' => 'Coordonnées manquantes'], 400);
        }

        try {
            $response = $this->httpClient->request('GET', 'https://nominatim.openstreetmap.org/reverse', [
                'query' => [
                    'lat' => $data['latitude'],
                    'lon' => $data['longitude'],
                    'format' => 'json',
                    'zoom' => 10,
                ],
                'headers' => [
                    'User-Agent' => 'ArdhiWEB/1.0',
                ],
            ]);

            return $this->json($response->toArray());
        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur Nominatim: ' . $e->getMessage()], 500);
        }
    }
}
