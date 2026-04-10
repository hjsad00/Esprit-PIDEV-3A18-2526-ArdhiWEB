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
    public function index(
        Request $request,
        LocationService $locationService,
        \App\Service\UserAndDiag\GamificationService $gamificationService,
        \App\Service\UserAndDiag\WeatherService $weatherService,
        \App\Service\UserAndDiag\WeatherAlertService $weatherAlertService,
        \App\Service\UserAndDiag\EpidemicAlertService $epidemicService
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        // Detect location via IP (same approach as Java desktop app)
        $locationData = $locationService->detectLocation($request->getClientIp());
        $locationLabel = $locationData ? $locationData['label'] : 'Localisation inconnue';

        // 1. Dynamic Gamification stats
        $points = $user->getPoints() ?? 0;
        $level = $user->getLevel() ?? 1;
        $basePoints = ($level - 1) * 500;
        $progress = (($points - $basePoints) / 500) * 100;

        // 2. Dynamic Badges
        $userBadges = $gamificationService->getUserBadges($user);

        // 3. Dynamic Leaderboard
        $leaderboardRaw = $gamificationService->getLeaderboard(5);
        $leaderboard = [];
        $userInLeaderboard = false;
        foreach ($leaderboardRaw as $index => $row) {
            $isYou = ($row['id'] === $user->getId());
            if ($isYou)
                $userInLeaderboard = true;

            $name = $isYou ? 'Vous' : $row['prenom'] . ' ' . substr($row['nom'], 0, 1) . '.';
            $leaderboard[] = [
                'rank' => $index + 1,
                'name' => $name,
                'points' => $row['points'],
                'level' => $row['level'],
                'isMe' => $isYou
            ];
        }

        // If user not in Top 5, append them at the end
        if (!$userInLeaderboard && count($leaderboardRaw) >= 5) {
            $leaderboard[] = [
                'rank' => '-',
                'name' => 'Vous',
                'points' => $points,
                'level' => $level,
                'isMe' => true
            ];
        }


        // 4. Real-time Weather & Alerts
        $lat = $locationData ? $locationData['latitude'] : 36.8065;
        $lon = $locationData ? $locationData['longitude'] : 10.1815;

        // 4. Real-time Weather & Alerts
        $currentWeather = $weatherService->getCurrentWeather($lat, $lon);
        if (!$currentWeather) {
            $currentWeather = [
                'icon' => '❌',
                'temperature' => '--',
                'apparentTemperature' => '--',
                'humidity' => '--',
                'windSpeed' => '--',
                'condition' => 'Météo indisponible',
                'advice' => 'Impossible de charger les données météo.'
            ];
        }
        $weatherRisks = $weatherAlertService->getDiseaseRiskAlerts($lat, $lon);
        $treatmentTiming = $weatherAlertService->getTreatmentTiming($lat, $lon);

        $regionalDiseases = $epidemicService->getActiveDiseases($lat, $lon, 25.0);
        $regionalAlerts = $epidemicService->getRegionalAlerts($lat, $lon, 25.0);
        $regionalStats = $epidemicService->getRegionalStats($lat, $lon, 25.0);

        return $this->render('UserAndDiag/client_dashboard/index.html.twig', [
            'user' => $user,
            'location' => $locationData,
            'gamification' => [
                'level' => $level,
                'points' => $points,
                'progress' => min(100, max(0, $progress)),
            ],
            'badges' => $userBadges,
            'leaderboard' => $leaderboard,

            'weather' => $currentWeather ?: [
                'icon' => '🌡️',
                'temperature' => '--',
                'apparentTemperature' => '--',
                'humidity' => '--',
                'windSpeed' => '--',
                'condition' => 'Météo indisponible',
                'advice' => 'Impossible de charger les données météo.'
            ],
            'regional_diseases' => $regionalDiseases,
            'treatment_timing' => $treatmentTiming['overallAdvice'] ?? 'Service indisponible',

            'epidemic_stats' => [
                'diseases_detected' => $regionalStats[0],
                'reports' => $regionalStats[1],
                'radius' => 25
            ],
            'regional_alerts' => $regionalAlerts,
            'predictive_risks' => $weatherRisks,
        ]);
    }

    #[Route('/user-and-diag/dashboard/stats', name: 'app_client_dashboard_stats', methods: ['GET'])]
    public function stats(
        Request $request,
        \App\Service\UserAndDiag\GamificationService $gamificationService,
        \App\Service\UserAndDiag\WeatherService $weatherService,
        \App\Service\UserAndDiag\WeatherAlertService $weatherAlertService,
        \App\Service\UserAndDiag\EpidemicAlertService $epidemicService,
        LocationService $locationService
    ): Response {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], 401);
        }

        $points = $user->getPoints() ?? 0;
        $level = $user->getLevel() ?? 1;
        $basePoints = ($level - 1) * 500;
        $progress = (($points - $basePoints) / 500) * 100;

        $userBadges = $gamificationService->getUserBadges($user);

        $leaderboardRaw = $gamificationService->getLeaderboard(5);
        $leaderboard = [];
        foreach ($leaderboardRaw as $index => $row) {
            $isYou = ($row['id'] === $user->getId());
            $name = $isYou ? 'Vous' : $row['prenom'] . ' ' . substr($row['nom'], 0, 1) . '.';
            $leaderboard[] = [
                'rank' => $index + 1,
                'name' => $name,
                'points' => $row['points'],
                'level' => $row['level'],
                'isMe' => $isYou
            ];
        }

        $locationData = $locationService->detectLocation($request->getClientIp());
        $lat = $locationData ? $locationData['latitude'] : 36.8065;
        $lon = $locationData ? $locationData['longitude'] : 10.1815;

        $currentWeather = $weatherService->getCurrentWeather($lat, $lon);
        $weatherRisks = $weatherAlertService->getDiseaseRiskAlerts($lat, $lon);
        $treatmentTiming = $weatherAlertService->getTreatmentTiming($lat, $lon);

        $regionalDiseases = $epidemicService->getActiveDiseases($lat, $lon, 25.0);
        $regionalStats = $epidemicService->getRegionalStats($lat, $lon, 25.0);

        return $this->json([
            'points' => $points,
            'level' => $level,
            'progress' => min(100, max(0, $progress)),
            'badges' => $userBadges,
            'leaderboard' => $leaderboard,
            'weather' => $currentWeather,
            'diseaseAlerts' => $regionalDiseases,
            'predictiveRisks' => $weatherRisks,
            'epidemicStats' => [
                'diseases' => $regionalStats[0],
                'reports' => $regionalStats[1]
            ],
            'treatmentTiming' => $treatmentTiming['overallAdvice'] ?? null
        ]);
    }
}
