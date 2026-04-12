<?php

namespace App\Controller\Evenement;

use App\Repository\Evenement\EvenementRepository;
use App\Service\Evenement\CalendarExportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handle Google Calendar OAuth workflow and event export.
 */
#[Route('/evenement/calendar')]
class CalendarController extends AbstractController
{
    public function __construct(
        private CalendarExportService $calendarService,
        private EvenementRepository   $evenementRepo
    ) {}

    /**
     * Redirect the user to Google's OAuth2 consent screen.
     *
     * GET /evenement/calendar/connect
     */
    #[Route('/connect', name: 'app_calendar_connect', methods: ['GET'])]
    public function connect(): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $authUrl = $this->calendarService->getAuthorizationUrl(
            $this->getUser()->getEmail()
        );

        return $this->redirect($authUrl);
    }

    /**
     * Google redirects back here with an authorization code.
     *
     * GET /evenement/calendar/callback?code=...&state=...
     */
    #[Route('/callback', name: 'app_calendar_callback', methods: ['GET'])]
    public function callback(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // CSRF-like state check
        $session       = $request->getSession();
        $expectedState = $session->get('google_calendar_oauth_state');
        $returnedState = $request->query->get('state');

        if (!$expectedState || $expectedState !== $returnedState) {
            $this->addFlash('danger', 'Connexion Google annulée ou état invalide.');
            return $this->redirectToRoute('app_evenement_index');
        }

        $code = $request->query->get('code');
        if (!$code) {
            $this->addFlash('danger', 'Code d\'autorisation manquant.');
            return $this->redirectToRoute('app_evenement_index');
        }

        $ok = $this->calendarService->handleOAuthCallback($code, $this->getUser()->getEmail());

        if ($ok) {
            $this->addFlash('success', '✅ Google Calendar connecté avec succès !');
        } else {
            $this->addFlash('danger', '❌ Échec de la connexion Google Calendar.');
        }

        // If we stored a "pending event" in the session, redirect there
        $pendingEventId = $session->get('calendar_pending_event_id');
        $session->remove('calendar_pending_event_id');
        $session->remove('google_calendar_oauth_state');

        if ($pendingEventId) {
            return $this->redirectToRoute('app_calendar_add', ['id' => $pendingEventId]);
        }

        return $this->redirectToRoute('app_evenement_index');
    }

    /**
     * Add an event to the user's Google Calendar (or download .ics as fallback).
     *
     * POST /evenement/calendar/add/{id}
     */
    #[Route('/add/{id}', name: 'app_calendar_add', methods: ['POST', 'GET'], requirements: ['id' => '\d+'])]
    public function add(int $id, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $evenement = $this->evenementRepo->find($id);
        if (!$evenement) {
            $this->addFlash('warning', 'Événement introuvable.');
            return $this->redirectToRoute('app_evenement_index');
        }

        $userEmail = $this->getUser()->getEmail();

        // If not connected yet, store intent and redirect to OAuth
        if (!$this->calendarService->isConnected($userEmail)) {
            $request->getSession()->set('calendar_pending_event_id', $id);
            return $this->redirectToRoute('app_calendar_connect');
        }

        $result = $this->calendarService->ajouterAuCalendrier($evenement, $userEmail);

        if ($result['mode'] === 'api') {
            // Added directly via API — show success with link
            $this->addFlash('success', '✅ Événement ajouté dans Google Calendar ! <a href="' . htmlspecialchars($result['link']) . '" target="_blank">Voir l\'événement</a>');
            return $this->redirectToRoute('app_evenement_show', ['id' => $id]);
        }

        // ICS fallback — stream the file as a download
        return $this->downloadICS($result['icsContent'], $result['filename']);
    }

    /**
     * Quick ICS download without Google auth (always available).
     *
     * GET /evenement/calendar/ics/{id}
     */
    #[Route('/ics/{id}', name: 'app_calendar_ics', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function downloadIcsRoute(int $id): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $evenement = $this->evenementRepo->find($id);
        if (!$evenement) {
            throw $this->createNotFoundException();
        }

        $icsContent = $this->calendarService->genererICS($evenement);
        $filename   = 'event_' . $evenement->getId() . '_' . time() . '.ics';

        return $this->downloadICS($icsContent, $filename);
    }

    /**
     * AJAX — Check if the current user is connected to Google Calendar.
     *
     * GET /evenement/calendar/status
     */
    #[Route('/status', name: 'app_calendar_status', methods: ['GET'])]
    public function status(): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->json([
            'connected' => $this->calendarService->isConnected($this->getUser()->getEmail()),
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function downloadICS(string $icsContent, string $filename): Response
    {
        return new Response($icsContent, 200, [
            'Content-Type'        => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }
}
