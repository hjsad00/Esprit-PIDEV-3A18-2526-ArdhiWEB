<?php

namespace App\Controller\Parcelles_Cultures\Farmer;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Entity\Parcelles_Cultures\Culture;
use App\Repository\Parcelles_Cultures\ParcelleRepository;
use App\Repository\Parcelles_Cultures\CultureRepository;
use App\Service\ExternalAPI\GeminiService;
use App\Service\ExternalAPI\WeatherService;
use App\Service\Parcelles_Cultures\IrrigationService;
use App\Service\Parcelles_Cultures\FinancialService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/farmer/recommendations', name: 'farmer_recommendations_')]
#[IsGranted('ROLE_AGRICULTEUR')]
class RecommendationController extends AbstractController
{
    public function __construct(
        private ParcelleRepository $parcelleRepository,
        private CultureRepository $cultureRepository,
        private GeminiService $geminiService,
        private WeatherService $weatherService,
        private IrrigationService $irrigationService,
        private FinancialService $financialService,
        private EntityManagerInterface $em
    ) {
    }

    /**
     * Page de recommandation de cultures
     */
    #[Route('/cultures', name: 'cultures', methods: ['GET'])]
    public function recommendCultures(): Response
    {
        $user = $this->getUser();
        $parcelles = $this->parcelleRepository->findByAgriculteur($user);

        return $this->render('parcelles_cultures/farmer/recommendations/cultures.html.twig', [
            'parcelles' => $parcelles,
        ]);
    }

    /**
     * API endpoint pour obtenir les recommandations de cultures via Gemini
     */
    #[Route('/api/cultures', name: 'api_cultures', methods: ['POST'], format: 'json')]
    public function apiCultureRecommendations(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['parcelleId'])) {
            return $this->json(['error' => 'ID de parcelle manquant'], 400);
        }

        $parcelle = $this->parcelleRepository->find($data['parcelleId']);
        if (!$parcelle || $parcelle->getAgriculteur() !== $this->getUser()) {
            return $this->json(['error' => 'Parcelle non trouvée ou accès refusé'], 404);
        }

        if (!$parcelle->getLatitude() || !$parcelle->getLongitude()) {
            return $this->json(['error' => 'Localisation manquante'], 400);
        }

        // Récupérer les données météo
        $weatherData = $this->weatherService->getWeatherData(
            (float) $parcelle->getLatitude(),
            (float) $parcelle->getLongitude()
        );

        if (isset($weatherData['error'])) {
            return $this->json(['error' => 'Erreur météo'], 500);
        }

        // Obtenir les recommandations Gemini
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
     * Sauvegarder les cultures recommandées
     */
    #[Route('/api/cultures/save', name: 'api_cultures_save', methods: ['POST'], format: 'json')]
    public function saveCulturesFromRecommendations(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['parcelleId'], $data['cultures']) || count($data['cultures']) === 0) {
            return $this->json(['error' => 'Données insuffisantes'], 400);
        }

        $parcelle = $this->parcelleRepository->find($data['parcelleId']);
        if (!$parcelle || $parcelle->getAgriculteur() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        try {
            foreach ($data['cultures'] as $cultData) {
                $culture = new Culture();
                $culture->setNomCulture($cultData['nom'] ?? 'Culture inconnue');
                $culture->setTypeCulture($cultData['type'] ?? 'Maraîcher');
                $culture->setSurfaceUtilisee((float)($cultData['surface'] ?? 0));
                $culture->setRendementEstime((float)($cultData['rendement_estime'] ?? 0));
                $culture->setSaison('Printemps'); // À adapter selon les recommandations
                $culture->setDatePlantation(new \DateTime());
                $culture->setDateRecoltePrevue((new \DateTime())->modify('+6 months'));
                $culture->setParcelle($parcelle);

                $this->em->persist($culture);
            }

            $this->em->flush();
            return $this->json(['success' => true, 'message' => 'Cultures enregistrées avec succès']);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Page de planification d'irrigation
     */
    #[Route('/irrigation', name: 'irrigation', methods: ['GET'])]
    public function irrigationPlanner(): Response
    {
        $user = $this->getUser();
        $parcelles = $this->parcelleRepository->findByAgriculteur($user);

        return $this->render('parcelles_cultures/farmer/recommendations/irrigation.html.twig', [
            'parcelles' => $parcelles,
        ]);
    }

    /**
     * API endpoint pour calculer les besoins en irrigation
     */
    #[Route('/api/irrigation', name: 'api_irrigation', methods: ['POST'], format: 'json')]
    public function apiCalculateIrrigation(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['parcelleId'], $data['cultureId'])) {
            return $this->json(['error' => 'Données manquantes'], 400);
        }

        $parcelle = $this->parcelleRepository->find($data['parcelleId']);
        $culture = $this->cultureRepository->find($data['cultureId']);

        if (!$parcelle || !$culture || $parcelle->getAgriculteur() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        if (!$parcelle->getLatitude() || !$parcelle->getLongitude()) {
            return $this->json(['error' => 'Localisation manquante'], 400);
        }

        try {
            $weatherData = $this->weatherService->getWeatherData(
                (float) $parcelle->getLatitude(),
                (float) $parcelle->getLongitude()
            );

            $calculation = $this->irrigationService->calculateIrrigationNeeds(
                $weatherData,
                $culture->getNomCulture(),
                (float) $culture->getSurfaceUtilisee()
            );

            return $this->json($calculation);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Page de calculateur financier ROI
     */
    #[Route('/roi', name: 'roi', methods: ['GET'])]
    public function roiCalculator(): Response
    {
        $user = $this->getUser();
        $cultures = $this->cultureRepository->findByAgriculteur($user);

        return $this->render('parcelles_cultures/farmer/recommendations/roi.html.twig', [
            'cultures' => $cultures,
        ]);
    }

    /**
     * API endpoint pour calculer le ROI
     */
    #[Route('/api/roi', name: 'api_roi', methods: ['POST'], format: 'json')]
    public function apiCalculateRoi(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['cultureId'], $data['costs'])) {
            return $this->json(['error' => 'Données manquantes'], 400);
        }

        $culture = $this->cultureRepository->find($data['cultureId']);
        if (!$culture || $culture->getParcelle()->getAgriculteur() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $parcelle = $culture->getParcelle();
        
        if (!$parcelle->getLatitude() || !$parcelle->getLongitude()) {
            return $this->json(['error' => 'Localisation manquante'], 400);
        }

        try {
            $weatherData = $this->weatherService->getWeatherData(
                (float) $parcelle->getLatitude(),
                (float) $parcelle->getLongitude()
            );

            $roiCalculation = $this->financialService->calculateRoi(
                $culture,
                $data['costs'],
                $data['salePrice'] ?? 0,
                $weatherData
            );

            return $this->json($roiCalculation);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }
}
