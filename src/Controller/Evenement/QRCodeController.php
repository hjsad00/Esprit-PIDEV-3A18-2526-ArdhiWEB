<?php

namespace App\Controller\Evenement;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\Participation;
use App\Repository\Evenement\EvenementRepository;
use App\Repository\Evenement\ParticipationRepository;
use App\Service\Evenement\EvenementStatusSyncService;
use App\Service\Evenement\QRCodeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Handles QR code display (participant view) and the check-in scanner (organiser view).
 * Ported from the Java QRCheckInController + QRCodeViewerController.
 */
#[Route('/evenement/qr')]
class QRCodeController extends AbstractController
{
    public function __construct(
        private QRCodeService            $qrCodeService,
        private EvenementStatusSyncService $eventStatusSync
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // PARTICIPANT SIDE — view / download your own QR code
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Show the QR code for a specific participation.
     * Only the owner (or admin) may view it.
     *
     * GET /evenement/qr/participation/{id}
     */
    #[Route('/participation/{id}', name: 'app_qr_viewer', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function viewer(Participation $participation): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        // Only the participant or an admin can view this QR
        if ($participation->getUtilisateur() !== $this->getUser()
            && !$this->isGranted('ROLE_ADMIN')
        ) {
            throw $this->createAccessDeniedException();
        }

        $qrBase64 = $this->qrCodeService->genererQRCodeBase64($participation);
        $token    = $participation->getQrCodeToken();
        $tokenDisplay = $token
            ? substr($token, 0, 8) . '••••••••' . substr($token, -4)
            : null;

        return $this->render('evenement/qr_viewer.html.twig', [
            'participation' => $participation,
            'evenement'     => $participation->getEvenement(),
            'qrBase64'      => $qrBase64,
            'tokenDisplay'  => $tokenDisplay,
        ]);
    }

    /**
     * Download the QR code SVG directly.
     *
     * GET /evenement/qr/participation/{id}/download
     */
    #[Route('/participation/{id}/download', name: 'app_qr_download', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function download(Participation $participation): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($participation->getUtilisateur() !== $this->getUser()
            && !$this->isGranted('ROLE_ADMIN')
        ) {
            throw $this->createAccessDeniedException();
        }

        $svg      = $this->qrCodeService->genererQRCodeSvg($participation);
        $filename = 'QRCode_' . str_replace(' ', '_', $participation->getNomComplet()) . '.svg';

        return new Response($svg, 200, [
            'Content-Type'        => 'image/svg+xml',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ORGANISER SIDE — check-in scanner dashboard
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * The QR check-in scanner page (ported from Java QRCheckInController).
     * Accessible by organisers (ROLE_AGRICULTEUR) and admins.
     *
     * GET /evenement/qr/checkin
     * GET /evenement/qr/checkin/{evenementId}   ← pre-select an event
     */
    #[Route('/checkin', name: 'app_qr_checkin', methods: ['GET'])]
    #[Route('/checkin/{evenementId}', name: 'app_qr_checkin_event', methods: ['GET'], requirements: ['evenementId' => '\d+'])]
    public function checkinDashboard(
        EvenementRepository $evenementRepo,
        int $evenementId = 0
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user   = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Build list of events this user can manage (same logic as Java loadEvenements)
        $allEvenements = $evenementRepo->findAll();
        $manageable    = array_filter($allEvenements, function (Evenement $e) use ($user, $isAdmin) {
            return $isAdmin || $e->getCreateur() === $user;
        });

        $selectedEvenement = null;
        if ($evenementId) {
            $selectedEvenement = $evenementRepo->find($evenementId);
        } elseif (!empty($manageable)) {
            $selectedEvenement = array_values($manageable)[0];
        }

        $stats         = $selectedEvenement
            ? $this->qrCodeService->getCheckInStats($selectedEvenement)
            : ['presents' => 0, 'inscrits' => 0, 'enAttente' => 0, 'taux' => 0];

        $template = $this->isGranted('ROLE_ADMIN')
            ? 'evenement/qr_checkin.html.twig'
            : 'evenement/qr_checkin_agriculteur.html.twig';

        return $this->render($template, [
            'evenements'        => array_values($manageable),
            'selectedEvenement' => $selectedEvenement,
            'stats'             => $stats,
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // AJAX endpoints (called by the check-in page via fetch)
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * AJAX — Process a scanned QR code string.
     *
     * POST /evenement/qr/scan
     * Body JSON: { "qrContent": "ARDHI_CHECKIN|token|P42|E7" }
     *       OR   { "qrContent": "rawtoken32chars" }
     * Response JSON: { "success": true, "message": "...", "participation": {...}, "stats": {...} }
     */
    #[Route('/scan', name: 'app_qr_scan', methods: ['POST'])]
    public function scan(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $data      = json_decode($request->getContent(), true) ?? [];
        $qrContent = trim($data['qrContent'] ?? '');

        if ($qrContent === '') {
            return $this->json(['success' => false, 'message' => '⚠️ Contenu QR vide']);
        }

        // Detect format (same as Java handleManualScan)
        $result = str_starts_with($qrContent, 'ARDHI_CHECKIN|')
            ? $this->qrCodeService->processQRScan($qrContent)
            : $this->qrCodeService->validateToken($qrContent);

        // Build response payload
        $payload = [
            'success' => $result['success'],
            'message' => $result['message'],
        ];

        if ($result['participation']) {
            /** @var Participation $p */
            $p = $result['participation'];
            $payload['participation'] = [
                'id'          => $p->getId(),
                'nomComplet'  => $p->getNomComplet(),
                'email'       => $p->getUtilisateur()?->getEmail(),
                'statut'      => $p->getStatut(),
            ];
        }

        // Refresh stats for the event so the dashboard updates live
        if ($result['evenement']) {
            $payload['stats'] = $this->qrCodeService->getCheckInStats($result['evenement']);
        }

        return $this->json($payload);
    }

    /**
     * AJAX — Manual check-in (button click in the participants table).
     *
     * POST /evenement/qr/checkin-manuel/{id}
     * Response JSON: { "success": true, "message": "...", "stats": {...} }
     */
    #[Route('/checkin-manuel/{id}', name: 'app_qr_checkin_manuel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function checkinManuel(Participation $participation, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $evenement = $participation->getEvenement();
        $user      = $this->getUser();
        $isAdmin   = $this->isGranted('ROLE_ADMIN');

        if (!$isAdmin && $evenement->getCreateur() !== $user) {
            return $this->json(['success' => false, 'message' => 'Non autorisé'], 403);
        }

        if ($participation->getStatut() === 'PRESENT') {
            return $this->json([
                'success' => false,
                'message' => '⚠️ ' . $participation->getNomComplet() . ' est déjà présent(e)',
                'stats'   => $this->qrCodeService->getCheckInStats($evenement),
            ]);
        }

        if ($participation->getStatut() === 'ANNULE') {
            return $this->json([
                'success' => false,
                'message' => '❌ L\'inscription de ' . $participation->getNomComplet() . ' est annulée',
                'stats'   => $this->qrCodeService->getCheckInStats($evenement),
            ]);
        }

        $participation->setStatut('PRESENT');
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => '✅ ' . $participation->getNomComplet() . ' — Check-in réussi !',
            'stats'   => $this->qrCodeService->getCheckInStats($evenement),
        ]);
    }

    /**
     * AJAX — Load participants for a specific event (for the scanner table).
     *
     * GET /evenement/qr/participants/{evenementId}
     * Response JSON: [ { id, nomComplet, email, statut }, ... ]
     */
    #[Route('/participants/{evenementId}', name: 'app_qr_participants', methods: ['GET'], requirements: ['evenementId' => '\d+'])]
    public function participants(
        int $evenementId,
        EvenementRepository $evenementRepo,
        ParticipationRepository $participationRepo,
        Request $request
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $evenement = $evenementRepo->find($evenementId);
        if (!$evenement) {
            return $this->json(['error' => 'Événement introuvable'], 404);
        }

        $search       = strtolower(trim($request->query->get('search', '')));
        $participations = $participationRepo->findBy(['evenement' => $evenement]);

        $data = [];
        foreach ($participations as $p) {
            $nom   = strtolower($p->getNomComplet() ?? '');
            $email = strtolower($p->getUtilisateur()?->getEmail() ?? '');

            if ($search && !str_contains($nom, $search) && !str_contains($email, $search)) {
                continue;
            }

            $data[] = [
                'id'         => $p->getId(),
                'nomComplet' => $p->getNomComplet(),
                'email'      => $p->getUtilisateur()?->getEmail(),
                'statut'     => $p->getStatut(),
            ];
        }

        return $this->json($data);
    }

    /**
     * AJAX — Refresh stats for an event.
     *
     * GET /evenement/qr/stats/{evenementId}
     * Response JSON: { presents, inscrits, enAttente, taux }
     */
    #[Route('/stats/{evenementId}', name: 'app_qr_stats', methods: ['GET'], requirements: ['evenementId' => '\d+'])]
    public function stats(int $evenementId, EvenementRepository $evenementRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $evenement = $evenementRepo->find($evenementId);
        if (!$evenement) {
            return $this->json(['error' => 'Événement introuvable'], 404);
        }

        return $this->json($this->qrCodeService->getCheckInStats($evenement));
    }
}
