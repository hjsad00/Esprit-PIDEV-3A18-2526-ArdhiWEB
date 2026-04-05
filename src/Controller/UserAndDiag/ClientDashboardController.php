<?php

namespace App\Controller\UserAndDiag;

use App\Service\UserAndDiag\LocationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ClientDashboardController extends AbstractController
{
    #[Route('/user-and-diag/dashboard', name: 'app_client_dashboard', methods: ['GET'])]
    public function index(Request $request, LocationService $locationService): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        // Detect location via IP (same approach as Java desktop app)
        $locationData = $locationService->detectLocation($request->getClientIp());
        $locationLabel = $locationData ? $locationData['label'] : 'Localisation inconnue';

        // Dummy data mimicking JavaFX state
        return $this->render('UserAndDiag/client_dashboard/index.html.twig', [
            'user' => $user,
            'location' => $locationData,
            'gamification' => [
                'level' => 3,
                'points' => 1250,
                'progress' => 50, // representing 50%
            ],
            'badges' => [
                ['icon' => '🌱', 'name' => 'Ferme Débutante', 'description' => 'A validé son premier scan.'],
                ['icon' => '🛡️', 'name' => 'Protecteur', 'description' => 'A appliqué 3 traitements préventifs.'],
                ['icon' => '🏆', 'name' => 'Expert', 'description' => 'Top 10 du classement.'],
            ],
            'weather' => [
                'icon' => '🌤️',
                'temperature' => '24',
                'feels_like' => '26.5',
                'humidity' => '55',
                'precipitation' => '0',
                'wind' => '12.5',
                'condition' => 'Éclaircies | ' . $locationLabel,
                'advice' => 'Conditions idéales pour l\'arrosage de fin de journée.'
            ],
            'epidemic_stats' => [
                'diseases_detected' => 2,
                'reports' => 5,
                'radius' => 25
            ],
            'regional_diseases' => [
                ['icon' => '🍂', 'name' => 'Mildiou de la Tomate', 'severity' => 'Élevé', 'distance' => 5.2],
                ['icon' => '🐛', 'name' => 'Puceron Vert', 'severity' => 'Modéré', 'distance' => 12.0],
            ],
            'regional_alerts' => [
                ['message' => 'Alerte: Propagation rapide du mildiou dans le gouvernorat voisin détectée il y a 48h.']
            ],
            'predictive_risks' => [
                ['icon' => '⚠️', 'type' => 'Oïdium', 'level' => 'Critique', 'reason' => 'Risque élevé dû à l\'humidité croissante (85%) et aux températures douces prévues cette nuit.', 'advice' => 'Envisagez un traitement préventif au soufre avant 18h.'],
            ],
            'treatment_timing' => '⏱️ Fenêtre de traitement optimale demain entre 06h00 et 09h00 (Vent faible < 5km/h, pas de pluie prévue).',
            'leaderboard' => [
                ['rank' => 1, 'name' => 'Ahmed T.', 'points' => 3450, 'level' => 7],
                ['rank' => 2, 'name' => 'Sami B.', 'points' => 3120, 'level' => 6],
                ['rank' => 3, 'name' => 'Karim F.', 'points' => 2890, 'level' => 6],
                ['rank' => 4, 'name' => 'Nawel M.', 'points' => 2750, 'level' => 5],
                ['rank' => 5, 'name' => 'Vous', 'points' => 1250, 'level' => 3],
            ]
        ]);
    }
}
