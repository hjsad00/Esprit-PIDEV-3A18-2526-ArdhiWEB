<?php

namespace App\Controller\Parcelles_Cultures\Farmer\API;

use App\Service\IrrigationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/irrigation', name: 'api_irrigation_')]
#[IsGranted('ROLE_USER')]
class IrrigationApiController extends AbstractController
{
    public function __construct(private IrrigationService $irrigationService) {}

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
}
