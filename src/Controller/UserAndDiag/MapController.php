<?php

namespace App\Controller\UserAndDiag;

use App\Repository\UserAndDiag\DiagnosticRepository;
use App\Service\UserAndDiag\EpidemicService;
use App\Service\UserAndDiag\LocationService;
use App\Service\UserAndDiag\NDVIService;
use App\Service\UserAndDiag\SporeCastService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/map')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MapController extends AbstractController
{
    #[Route('', name: 'app_user_and_diag_map', methods: ['GET'])]
    public function index(Request $request, LocationService $locationService, EpidemicService $epidemicService): Response
    {
        // Detect user location for center
        $loc = $locationService->detectLocation();
        $lat = $loc['latitude'] ?? 36.8065;
        $lon = $loc['longitude'] ?? 10.1815;

        $regionalDiseases = $epidemicService->getActiveDiseases($lat, $lon, 50.0);

        return $this->render('UserAndDiag/diagnostic/map.html.twig', [
            'centerLat' => $lat,
            'centerLon' => $lon,
            'regionalDiseases' => $regionalDiseases
        ]);
    }

    #[Route('/data', name: 'app_user_and_diag_map_data', methods: ['GET'])]
    public function getMapData(DiagnosticRepository $diagnosticRepository, LocationService $locationService): JsonResponse
    {
        $diagnostics = $diagnosticRepository->findWithLocation();

        $loc = $locationService->detectLocation();
        $centerLat = $loc['latitude'] ?? 36.8065;
        $centerLon = $loc['longitude'] ?? 10.1815;

        $features = [];
        foreach ($diagnostics as $d) {
            $severity = $this->calculateSeverity($d->getConfiance());

            $lat = $d->getLatitude();
            $lon = $d->getLongitude();

            // Default to center location with tiny random jitter for display if no actual coordinates exist
            if ($lat === null || $lon === null) {
                // +/- ~0.05 deg jitter
                $lat = $centerLat + (mt_rand(-500, 500) / 10000.0);
                $lon = $centerLon + (mt_rand(-500, 500) / 10000.0);
            }

            $features[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => 'Point',
                    'coordinates' => [$lon, $lat]
                ],
                'properties' => [
                    'id' => $d->getId(),
                    'title' => $this->extractDisease($d->getResultatIa()),
                    'resultat' => $d->getResultatIa(),
                    'date' => $d->getDateScan()->format('d/m/Y'),
                    'confiance' => round($d->getConfiance(), 1),
                    'location' => $d->getLocationLabel() ?: 'Localisation estimée',
                    'severity' => $severity,
                    'image' => $d->getImageScannee()
                ]
            ];
        }

        return $this->json([
            'type' => 'FeatureCollection',
            'features' => $features
        ]);
    }

    #[Route('/ndvi', name: 'app_user_and_diag_map_ndvi', methods: ['GET'])]
    public function getNdviData(Request $request, NDVIService $ndviService): JsonResponse
    {
        $lat = (float) $request->query->get('lat', 36.8065);
        $lon = (float) $request->query->get('lon', 10.1815);

        $cells = $ndviService->fetchSimulatedNDVIGrid($lat, $lon, 15.0, 8);
        return $this->json($cells);
    }

    #[Route('/sporecast', name: 'app_user_and_diag_map_sporecast', methods: ['GET'])]
    public function getSporeCast(Request $request, SporeCastService $sporeCastService): JsonResponse
    {
        $lat = (float) $request->query->get('lat');
        $lon = (float) $request->query->get('lon');
        $disease = $request->query->get('disease', '');

        $result = $sporeCastService->analyzeSourceLocation($lat, $lon, $disease);
        return $this->json($result);
    }

    private function calculateSeverity(float $confiance): string
    {
        if ($confiance >= 80)
            return 'CRITICAL';
        if ($confiance >= 50)
            return 'MEDIUM';
        return 'LOW';
    }

    private function extractDisease(?string $fullResult): string
    {
        if (!$fullResult)
            return 'Diagnostic';
        if (str_contains($fullResult, ' - ')) {
            $parts = explode(' - ', $fullResult);
            return trim($parts[1]);
        }
        return $fullResult;
    }
}
