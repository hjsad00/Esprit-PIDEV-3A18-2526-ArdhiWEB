<?php

namespace App\Controller\Parcelles_Cultures\Farmer\API;

use App\Service\IrrigationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/api/irrigation', name: 'api_irrigation_')]
#[IsGranted('ROLE_USER')]
class IrrigationApiController extends AbstractController
{
    public function __construct(
        private IrrigationService $irrigationService,
        private HttpClientInterface $httpClient
    ) {}

    #[Route('/calculate', name: 'calculate', methods: ['POST'])]
    public function calculate(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            // Validation des paramètres
            $latitude = (float) ($data['latitude'] ?? 0);
            $longitude = (float) ($data['longitude'] ?? 0);
            $culture = (string) ($data['culture'] ?? 'default');
            $surface = (float) ($data['surface'] ?? 1);
            $saison = (string) ($data['saison'] ?? null);

            if (!$latitude || !$longitude) {
                return $this->json(['error' => 'Latitude et longitude requises'], 400);
            }

            if ($surface <= 0) {
                return $this->json(['error' => 'Surface invalide'], 400);
            }

            // Calcul
            $result = $this->irrigationService->calculateIrrigationNeeds(
                $latitude,
                $longitude,
                $culture,
                $surface,
                $saison
            );

            return $this->json($result);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/weather', name: 'weather', methods: ['GET'])]
    public function getWeather(Request $request): JsonResponse
    {
        try {
            $latitude = (float) ($request->query->get('latitude') ?? 0);
            $longitude = (float) ($request->query->get('longitude') ?? 0);

            if (!$latitude || !$longitude) {
                return $this->json(['error' => 'Latitude et longitude requises'], 400);
            }

            // Récupérer les données météo actuelles et prévisions
            $response = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'current' => 'temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m,precipitation',
                    'daily' => 'temperature_2m_max,temperature_2m_min,precipitation_sum,weather_code',
                    'forecast_days' => 5,
                    'timezone' => 'auto',
                ]
            ]);

            $data = $response->toArray();
            
            return $this->json([
                'current' => $data['current'] ?? [],
                'daily' => $data['daily'] ?? [],
                'timezone' => $data['timezone'] ?? 'UTC',
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
