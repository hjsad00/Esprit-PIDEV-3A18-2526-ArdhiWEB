<?php

namespace App\Controller\Evenement;

use App\Service\Evenement\InactiveParticipantDetectionService;
use App\Service\Evenement\SmartEmailRecommendationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handle the dashboard for at-risk participants and automated re-engagement.
 */
#[Route('/evenement/inactifs')]
class InactiveParticipantController extends AbstractController
{
    public function __construct(
        private InactiveParticipantDetectionService $detectionService,
        private SmartEmailRecommendationService     $emailService
    ) {}

    /**
     * Dashboard — lists all at-risk profiles with their scores and indicators.
     *
     * GET /evenement/inactifs
     */
    #[Route('', name: 'app_inactifs_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $creator  = $this->isGranted('ROLE_ADMIN') ? null : $this->getUser();
        $profiles = $this->detectionService->detecterParticipantsInactifs($creator);
        $globalStats = $this->detectionService->genererStatistiques($profiles);

        $template = $this->isGranted('ROLE_ADMIN')
            ? 'evenement/inactifs_dashboard.html.twig'
            : 'evenement/inactifs_agriculteur.html.twig';

        return $this->render($template, [
            'profiles'    => $profiles,
            'globalStats' => $globalStats,
        ]);
    }

    /**
     * AJAX — Send a re-engagement email to a single user by their profile index.
     *
     * POST /evenement/inactifs/relance/{userId}
     * Response JSON: { success, message }
     */
    #[Route('/relance/{userId}', name: 'app_inactifs_relance_single', methods: ['POST'], requirements: ['userId' => '\d+'])]
    public function relanceSingle(int $userId): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $creator  = $this->isGranted('ROLE_ADMIN') ? null : $this->getUser();
        $profiles = $this->detectionService->detecterParticipantsInactifs($creator);
        $profile  = null;
        foreach ($profiles as $p) {
            if ($p['userId'] === $userId) { $profile = $p; break; }
        }

        if (!$profile) {
            return $this->json(['success' => false, 'message' => 'Profil introuvable'], 404);
        }

        $ok = $this->emailService->envoyerRelancePersonnalisee($profile);

        return $this->json([
            'success' => $ok,
            'message' => $ok
                ? '✅ Email envoyé à ' . $profile['email']
                : '❌ Échec de l\'envoi à ' . $profile['email'],
        ]);
    }

    /**
     * AJAX — Send re-engagement emails to ALL at-risk profiles (score ≥ 30).
     *
     * POST /evenement/inactifs/relances-auto
     * Response JSON: { urgentes, importantes, standard, total, echecs }
     */
    #[Route('/relances-auto', name: 'app_inactifs_relances_auto', methods: ['POST'])]
    public function relancesAuto(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $creator  = $this->isGranted('ROLE_ADMIN') ? null : $this->getUser();
        $profiles = $this->detectionService->detecterParticipantsInactifs($creator);
        $stats    = $this->emailService->envoyerRelancesAutomatiques($profiles);

        return $this->json($stats);
    }

    /**
     * AJAX — Reload stats only (for live refresh).
     *
     * GET /evenement/inactifs/stats
     */
    #[Route('/stats', name: 'app_inactifs_stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $creator  = $this->isGranted('ROLE_ADMIN') ? null : $this->getUser();
        $profiles = $this->detectionService->detecterParticipantsInactifs($creator);
        return $this->json($this->detectionService->genererStatistiques($profiles));
    }
}
