<?php

namespace App\Controller\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Maintenance;
use App\Form\MaterielEtMaintenance\MaintenanceType;
use App\Repository\MaterielEtMaintenance\MaintenanceRepository;
use App\Repository\MaterielEtMaintenance\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/materiel-et-maintenance/maintenance', name: 'app_maintenance_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MaintenanceController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, MaintenanceRepository $repo, MaterielRepository $materielRepo): Response
    {
        $userId = $this->getUser()->getId();
        $type   = $request->query->get('type', '');
        $statut = $request->query->get('statut', '');
        $search = $request->query->get('search', '');

        $maintenances = $repo->searchByUser($userId, $type ?: null, $statut ?: null, $search ?: null);
        $stats        = $repo->getStatsByUser($userId);
        $materielStats = $materielRepo->getStatsByUser($userId);

        return $this->render('MaterielEtMaintenance/maintenance/index.html.twig', [
            'maintenances'  => $maintenances,
            'stats'         => $stats,
            'materielStats' => $materielStats,
            'type'          => $type,
            'statut'        => $statut,
            'search'        => $search,
            'enRetard'      => $materielRepo->findEnRetardByUser($userId),
        ]);
    }

    #[Route('/planifier', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, \App\Service\GoogleCalendarService $googleCalendar): Response
    {
        $maintenance = new Maintenance();
        $form = $this->createForm(MaintenanceType::class, $maintenance, [
            'user_id' => $this->getUser()->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($maintenance);

            // Mettre à jour dernière/prochaine maintenance du matériel
            $materiel = $maintenance->getMateriel();
            if ($maintenance->getStatutMaintenance() === 'terminee') {
                $materiel->setDerniereMaintenance($maintenance->getDateMaintenance());
                if ($materiel->getFrequenceMaintenanceMois()) {
                    $prochaine = clone $maintenance->getDateMaintenance();
                    $prochaine->modify('+' . $materiel->getFrequenceMaintenanceMois() . ' months');
                    $materiel->setDateProchaineMaintenance($prochaine);
                }
            }

            // Google Calendar Sync
            if ($maintenance->getDateMaintenance() && $this->getUser()->getGoogleAccessToken()) {
                $eventId = $googleCalendar->createMaintenanceEvent(
                    $this->getUser(),
                    $maintenance->getMateriel()->getNom(),
                    $maintenance->getDescription() ?? 'Intervention planifiée.',
                    $maintenance->getDateMaintenance()
                );
                
                if ($eventId) {
                    $maintenance->setGoogleCalendarEventId($eventId);
                    $this->addFlash('success', 'Maintenance planifiée et ajoutée à Google Calendar !');
                } else {
                    $this->addFlash('warning', 'Maintenance planifiée, mais échec de la synchronisation avec Google Calendar (Token expiré ou erreur).');
                }
            } else {
                $this->addFlash('success', 'Maintenance planifiée avec succès !');
            }

            $em->flush();
            return $this->redirectToRoute('app_maintenance_index');
        }

        return $this->render('MaterielEtMaintenance/maintenance/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(int $id, MaintenanceRepository $repo): Response
    {
        $maintenance = $this->getMaintenanceOwnedByUser($id, $repo);
        return $this->render('MaterielEtMaintenance/maintenance/show.html.twig', [
            'maintenance' => $maintenance,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, MaintenanceRepository $repo, EntityManagerInterface $em): Response
    {
        $maintenance = $this->getMaintenanceOwnedByUser($id, $repo);
        $form = $this->createForm(MaintenanceType::class, $maintenance, [
            'user_id' => $this->getUser()->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Si terminée, update matériel
            $materiel = $maintenance->getMateriel();
            if ($maintenance->getStatutMaintenance() === 'terminee' && $maintenance->getDateMaintenance()) {
                $materiel->setDerniereMaintenance($maintenance->getDateMaintenance());
                if ($materiel->getFrequenceMaintenanceMois()) {
                    $prochaine = clone $maintenance->getDateMaintenance();
                    $prochaine->modify('+' . $materiel->getFrequenceMaintenanceMois() . ' months');
                    $materiel->setDateProchaineMaintenance($prochaine);
                }
            }

            $em->flush();
            $this->addFlash('success', 'Maintenance mise à jour avec succès !');
            return $this->redirectToRoute('app_maintenance_index');
        }

        return $this->render('MaterielEtMaintenance/maintenance/edit.html.twig', [
            'form' => $form->createView(),
            'maintenance' => $maintenance,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, MaintenanceRepository $repo, EntityManagerInterface $em): Response
    {
        $maintenance = $this->getMaintenanceOwnedByUser($id, $repo);

        if ($this->isCsrfTokenValid('delete_maintenance_' . $id, $request->request->get('_token'))) {
            $em->remove($maintenance);
            $em->flush();
            $this->addFlash('success', 'Maintenance supprimée.');
        } else {
            $this->addFlash('danger', 'Action non autorisée.');
        }

        return $this->redirectToRoute('app_maintenance_index');
    }

    private function getMaintenanceOwnedByUser(int $id, MaintenanceRepository $repo): Maintenance
    {
        $maintenance = $repo->find($id);
        if (!$maintenance || $maintenance->getMateriel()->getUserId() !== $this->getUser()->getId()) {
            throw $this->createNotFoundException('Maintenance introuvable.');
        }
        return $maintenance;
    }
}
