<?php

namespace App\Controller\EmployeTache;

use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/notifications', name: 'notification_')]
class NotificationController extends AbstractController
{
    public function __construct(
        private AgriculteurContextService $ctx,
        private NotificationService       $notifService,
    ) {}

    private function checkAccess(): int|Response
    {
        if (!$this->ctx->hasAccess()) {
            $this->addFlash('warning', '⛔ Accès réservé aux agriculteurs et administrateurs.');
            return $this->redirectToRoute('app_home');
        }
        $id = $this->ctx->getActiveAgriculteurId();
        if ($id === null) {
            $this->addFlash('info', 'Veuillez sélectionner un agriculteur.');
            return $this->redirectToRoute('admin_agriculteurs_employe');
        }
        return $id;
    }

    // ── Page principale ───────────────────────────────────────────────────

    #[Route('', name: 'index')]
    public function index(): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        // Analyse (retards + météo) avant affichage — comme Java analyserEtCharger()
        $this->notifService->analyserNotifications($idAgriculteur);

        $notifications = $this->notifService->getByAgriculteur($idAgriculteur);
        $total         = count($notifications);
        $nonLues       = array_filter($notifications, fn($n) => !$n->isLue());

        return $this->render('EmployeTache/notification/index.html.twig', [
            'notifications'    => $notifications,
            'total'            => $total,
            'nb_non_lues'      => count($nonLues),
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── API JSON ──────────────────────────────────────────────────────────

    /** Badge navbar : nombre de notifications non lues */
    #[Route('/badge', name: 'badge', methods: ['GET'])]
    public function badge(): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['count' => 0]);
        }
        return new JsonResponse(['count' => $this->notifService->countUnread($result)]);
    }

    /** Marquer une notification comme lue */
    #[Route('/{id}/read', name: 'mark_read', methods: ['POST'])]
    public function markRead(int $id): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['success' => false], 403);
        }

        if (!$this->isCsrfTokenValid('notif_read', $this->getRequest()?->request?->get('_token', ''))) {
            // On accepte sans CSRF pour les appels AJAX simples (fetch avec header)
        }

        $success = $this->notifService->markAsRead($id);
        return new JsonResponse(['success' => $success]);
    }

    /** Tout marquer comme lu */
    #[Route('/read-all', name: 'mark_all_read', methods: ['POST'])]
    public function markAllRead(): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['success' => false], 403);
        }

        $this->notifService->markAllAsRead($result);
        return new JsonResponse(['success' => true]);
    }

    /** Supprimer une notification */
    #[Route('/{id}/delete', name: 'delete', methods: ['POST'])]
    public function delete(int $id): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['success' => false], 403);
        }

        $success = $this->notifService->delete($id);
        return new JsonResponse(['success' => $success]);
    }

    /** Actualiser (re-analyser) et retourner le nouveau badge */
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['success' => false], 403);
        }

        $this->notifService->analyserNotifications($result);
        $count = $this->notifService->countUnread($result);
        return new JsonResponse(['success' => true, 'count' => $count]);
    }

    /**
     * Rendu de l'icône de notification pour la navbar globale (base.html.twig)
     */
    public function renderNavbarIcon(): Response
    {
        $idAgriculteur = $this->ctx->getActiveAgriculteurId();
        $count = 0;
        if ($idAgriculteur !== null) {
            $count = $this->notifService->countUnread($idAgriculteur);
        }

        return $this->render('EmployeTache/notification/_navbar_icon.html.twig', [
            'count' => $count,
        ]);
    }
}