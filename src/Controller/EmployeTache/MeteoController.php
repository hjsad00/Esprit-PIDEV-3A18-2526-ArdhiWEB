<?php

namespace App\Controller\EmployeTache;

use App\Repository\EmployeTache\TacheRepository;
use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\MeteoService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/employe-tache/meteo', name: 'meteo_')]
class MeteoController extends AbstractController
{
    /**
     * Render the weather widget directly for inclusion in Twig templates.
     */
    public function widgetMeteo(
        MeteoService $meteoService,
        TacheRepository $tacheRepository,
        AgriculteurContextService $contextService
    ): Response {
        $idAgriculteur = $contextService->getActiveAgriculteurId();

        if ($idAgriculteur === null) {
            return new Response(''); // Do not render anything if no active agriculteur
        }

        // 1. Get current weather
        $weatherData = $meteoService->getCurrentWeather();
        
        $recommandations = [];

        // 2. Compute recommendations if weather is available
        if ($weatherData->isAvailable()) {
            // A. Recommandations Proactives (Générales)
            foreach ($meteoService->genererRecommandationsGenerales($weatherData) as $reco) {
                $recommandations[] = $reco;
            }

            // B. Recommandations liées aux tâches du jour
            $tachesDuJour = $tacheRepository->findTachesDuJour($idAgriculteur);
            foreach ($tachesDuJour as $tache) {
                foreach ($meteoService->analyserConditionsPourTache($tache, $weatherData) as $reco) {
                    $recommandations[] = $reco;
                }
            }
        }

        return $this->render('EmployeTache/meteo/_widget.html.twig', [
            'weather' => $weatherData,
            'recommandations' => $recommandations,
        ]);
    }
}
