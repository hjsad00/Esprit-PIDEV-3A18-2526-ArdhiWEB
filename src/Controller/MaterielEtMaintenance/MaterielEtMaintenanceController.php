<?php

namespace App\Controller\MaterielEtMaintenance;

use App\Repository\MaterielEtMaintenance\NotificationMaintenanceRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MaterielEtMaintenanceController extends AbstractController
{
    #[Route('/materiel-et-maintenance', name: 'app_materiel_et_maintenance')]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(NotificationMaintenanceRepository $notificationRepo): Response
    {
        $user = $this->getUser();
        $unreadCount = $notificationRepo->countUnreadForUser($this->getUser());
        $recentNotifs = $notificationRepo->findBy(['user' => $user], ['createdAt' => 'DESC'], 5);

        return $this->render('MaterielEtMaintenance/landing.html.twig', [
            'unreadCount' => $unreadCount,
            'recentNotifs' => $recentNotifs,
        ]);
    }
}
