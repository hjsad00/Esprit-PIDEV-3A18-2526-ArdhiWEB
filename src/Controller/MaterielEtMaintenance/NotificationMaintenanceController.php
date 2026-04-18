<?php

namespace App\Controller\MaterielEtMaintenance;

use App\Repository\MaterielEtMaintenance\NotificationMaintenanceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/notification-maintenance', name: 'app_notification_maintenance_')]
#[IsGranted('ROLE_USER')]
class NotificationMaintenanceController extends AbstractController
{
    #[Route('/_bell', name: 'bell', methods: ['GET'])]
    public function renderBell(NotificationMaintenanceRepository $repo): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return new Response('');
        }

        $allNotifs = $repo->findBy(['user' => $user], ['createdAt' => 'DESC']);
        $unreadCount = 0;
        $recentNotifs = [];

        foreach ($allNotifs as $n) {
            if (!$n->isRead()) {
                $unreadCount++;
            }
            if (count($recentNotifs) < 5) {
                $recentNotifs[] = $n;
            }
        }

        return $this->render('MaterielEtMaintenance/notification/_bell.html.twig', [
            'recentNotifs' => $recentNotifs,
            'unreadCount' => $unreadCount,
        ]);
    }

    #[Route('/', name: 'index', methods: ['GET'])]
    public function index(NotificationMaintenanceRepository $repo, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $notifications = $repo->findBy(['user' => $user], ['createdAt' => 'DESC']);

        return $this->render('MaterielEtMaintenance/notification/index.html.twig', [
            'notifications' => $notifications,
        ]);
    }

    #[Route('/{id}/read', name: 'read', methods: ['GET'])]
    public function read(int $id, NotificationMaintenanceRepository $repo, EntityManagerInterface $em): Response
    {
        $notification = $repo->find($id);

        if ($notification && $notification->getUser() === $this->getUser()) {
            $notification->setRead(true);
            $em->flush();

            $materiel = $notification->getMateriel();
            if ($materiel) {
                // Redirige vers la page des détails du matériel.
                // Note: assuming 'app_materiel_show' exists, change if the route name is different.
                return $this->redirectToRoute('app_materiel_show', ['id' => $materiel->getId()]);
            }
        }

        return $this->redirectToRoute('app_notification_maintenance_index');
    }
}
