<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Repository\Parcelles_Cultures\ParcelleRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/weather', name: 'api_weather_')]
class WeatherController extends AbstractController
{
    #[Route('/get-coordinates/{parcelleId}', name: 'get_coordinates', methods: ['GET'])]
    public function getCoordinates(
        int $parcelleId,
        ParcelleRepository $parcelleRepo
    ): JsonResponse {
        $parcelle = $parcelleRepo->find($parcelleId);

        if (!$parcelle) {
            return $this->json(['error' => 'Parcelle not found'], 404);
        }

        // Retourner lat/lon de la parcelle
        return $this->json([
            'id' => $parcelle->getId(),
            'latitude' => $parcelle->getLatitude(),
            'longitude' => $parcelle->getLongitude(),
            'localisation' => $parcelle->getLocalisation(),
            'success' => true
        ]);
    }
}
