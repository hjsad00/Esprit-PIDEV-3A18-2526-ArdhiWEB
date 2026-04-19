<?php

namespace App\Controller\UserAndDiag;

use App\Service\UserAndDiag\SoilDataService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/soil-analysis')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class VirtualSoilController extends AbstractController
{
    #[Route('', name: 'app_user_and_diag_soil_analysis', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('UserAndDiag/soil_analysis/index.html.twig');
    }

    #[Route('/api', name: 'app_user_and_diag_soil_api', methods: ['GET'])]
    public function api(Request $request, SoilDataService $soilDataService): JsonResponse
    {
        $lat = (float) $request->query->get('lat', 36.8);
        $lon = (float) $request->query->get('lon', 10.18);

        $layers = $soilDataService->fetchSoilData($lat, $lon);

        return $this->json([
            'success' => true,
            'layers' => $layers,
        ]);
    }
}
