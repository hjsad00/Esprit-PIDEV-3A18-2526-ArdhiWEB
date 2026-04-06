<?php

namespace App\Controller\EmployeTache;

use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EmployeDashboardController extends AbstractController
{
    public function __construct(
        private AgriculteurContextService $ctx,
        private NotificationService       $notifService,
    ) {}

    #[Route('/employe-dashboard', name: 'app_employe_dashboard')]
    public function index(): Response
    {
        $idAgriculteur = $this->ctx->getActiveAgriculteurId();

        $nbNotifs = 0;
        if ($idAgriculteur !== null) {
            $this->notifService->analyserNotifications($idAgriculteur);
            $nbNotifs = $this->notifService->countUnread($idAgriculteur);
        }

        return $this->render('EmployeTache/viewagriculteure.html.twig', [
            'nb_notif_non_lues' => $nbNotifs,
        ]);
    }
}