<?php

namespace App\Controller\Admin;

use App\Entity\MaterielEtMaintenance\Maintenance;
use App\Repository\MaterielEtMaintenance\MaintenanceRepository;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/maintenance', name: 'admin_maintenance_')]
#[IsGranted('ROLE_ADMIN')]
class AdminMaintenanceController extends AbstractController
{
    #[Route('/timeline', name: 'timeline', methods: ['GET'])]
    public function timeline(Request $request, MaintenanceRepository $repo, UserRepository $userRepo, \App\Repository\MaterielEtMaintenance\MaterielRepository $matRepo): Response
    {
        $materielId = $request->query->get('materiel_id');
        $materielFilter = null;

        if ($materielId) {
            $materielFilter = $matRepo->find($materielId);
            $maintenances = $repo->findBy(['materiel' => $materielFilter], ['date_maintenance' => 'DESC']);
        } else {
            $maintenances = $repo->findAllOrderedByDate();
        }
        
        $users = $userRepo->findAll();
        $userMap = [];
        foreach ($users as $u) {
            $userMap[$u->getId()] = $u;
        }

        return $this->render('admin/maintenance/timeline.html.twig', [
            'maintenances' => $maintenances,
            'userMap' => $userMap,
            'materielFilter' => $materielFilter,
        ]);
    }

    #[Route('/{id}/status', name: 'status_update', methods: ['POST'])]
    public function updateStatus(int $id, Request $request, MaintenanceRepository $repo, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        $maintenance = $repo->find($id);

        if (!$maintenance) {
            throw $this->createNotFoundException('Maintenance introuvable.');
        }

        if ($this->isCsrfTokenValid('update_status_' . $maintenance->getIdMaintenance(), $request->request->get('_token'))) {
            $nouveauStatut = $request->request->get('statut_maintenance');
            $allowedStatuses = ['planifiee', 'en_attente', 'en_cours', 'verifie', 'terminee', 'annulee'];

            if (in_array($nouveauStatut, $allowedStatuses)) {
                $maintenance->setStatutMaintenance($nouveauStatut);
                
                if ($nouveauStatut === 'terminee') {
                    $maintenance->setDateRealisee(new \DateTime());
                    // Mettre à jour la date de dernière maintenance du matériel
                    $materiel = $maintenance->getMateriel();
                    $dateCible = $maintenance->getDateRealisee() ?: $maintenance->getDateMaintenance();
                    if ($dateCible) {
                        $materiel->setDerniereMaintenance($dateCible);
                        $materiel->calculerProchaineMaintenance();
                    }
                }

                // Notification pour le propriétaire du matériel
                $materiel = $maintenance->getMateriel();
                if ($materiel) {
                    $userId = $materiel->getUserId();
                    $user = $userRepo->find($userId);
                    if ($user) {
                        $notif = new \App\Entity\MaterielEtMaintenance\NotificationMaintenance();
                        $notif->setUser($user);
                        $notif->setMateriel($materiel);
                        $notif->setNouveauStatut($nouveauStatut);
                        $em->persist($notif);
                    }
                }

                $em->flush();
                $this->addFlash('success', 'Statut mis à jour ! Côté agriculteur, la modification est désormais visible.');
            } else {
                $this->addFlash('danger', 'Statut invalide.');
            }
        } else {
            $this->addFlash('danger', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('admin_maintenance_timeline');
    }

    #[Route('/historique', name: 'historique', methods: ['GET'])]
    public function historique(\App\Repository\MaterielEtMaintenance\MaterielRepository $matRepo, UserRepository $userRepo): Response
    {
        $materiels = $matRepo->findAll();
        
        $users = $userRepo->findAll();
        $userMap = [];
        foreach ($users as $u) {
            $userMap[$u->getId()] = $u;
        }

        return $this->render('admin/maintenance/historique.html.twig', [
            'materiels' => $materiels,
            'userMap' => $userMap,
        ]);
    }
}
