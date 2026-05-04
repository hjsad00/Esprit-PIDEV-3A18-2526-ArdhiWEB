<?php

namespace App\Controller\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Maintenance;
use App\Form\MaterielEtMaintenance\MaintenanceType;
use App\Repository\MaterielEtMaintenance\MaintenanceRepository;
use App\Repository\MaterielEtMaintenance\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;
use App\Service\MaterielEtMaintenance\GoogleCalendarService;

#[Route('/materiel-et-maintenance/maintenance', name: 'app_maintenance_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class MaintenanceController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(Request $request, MaintenanceRepository $repo, MaterielRepository $materielRepo, \App\Repository\MaterielEtMaintenance\AlerteTechnicienRepository $alerteRepo): Response
    {
        $userId = $this->getUser()->getId();
        $type   = $request->query->get('type', '');
        $statut = $request->query->get('statut', '');
        $search = $request->query->get('search', '');

        $maintenances = $repo->searchByUser($userId, $type ?: null, $statut ?: null, $search ?: null);
        $totalUnreadCount = $alerteRepo->countUnreadForAgriculteur($userId);
        
        $stats        = $repo->getStatsByUser($userId);
        $materielStats = $materielRepo->getStatsByUser($userId);

        return $this->render('MaterielEtMaintenance/maintenance/index.html.twig', [
            'maintenances'      => $maintenances,
            'stats'             => $stats,
            'materielStats'     => $materielStats,
            'type'              => $type,
            'statut'            => $statut,
            'search'            => $search,
            'totalUnreadCount'  => $totalUnreadCount,
            'enRetard'          => $materielRepo->findEnRetardByUser($userId),
        ]);
    }

    #[Route('/planifier', name: 'new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, GoogleCalendarService $googleCalendar, MailerInterface $mailer, Environment $twig): Response
    {
        $maintenance = new Maintenance();
        $form = $this->createForm(MaintenanceType::class, $maintenance, [
            'user_id' => $this->getUser()->getId(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Statut automatiquement "planifiée" à la création
            $maintenance->setStatutMaintenance('planifiee');

            $em->persist($maintenance);

            // Mettre à jour dernière/prochaine maintenance du matériel
            $materiel = $maintenance->getMateriel();
            if ($maintenance->getStatutMaintenance() === 'terminee') {
                $materiel->setDerniereMaintenance($maintenance->getDateMaintenance());
                $materiel->calculerProchaineMaintenance();
            }

            $em->flush();

            // --- Envoi email de confirmation avec PDF ---
            try {
                $user = $this->getUser();
                $userEmail = method_exists($user, 'getEmail') ? $user->getEmail() : null;

                if ($userEmail) {
                    // Générer le PDF
                    $pdfHtml = $twig->render('MaterielEtMaintenance/maintenance/pdf_confirmation.html.twig', [
                        'maintenance' => $maintenance,
                        'user'        => $user,
                    ]);

                    $options = new Options();
                    $options->set('isHtml5ParserEnabled', true);
                    $options->set('isRemoteEnabled', false);
                    $dompdf = new Dompdf($options);
                    $dompdf->loadHtml($pdfHtml);
                    $dompdf->setPaper('A4', 'portrait');
                    $dompdf->render();
                    $pdfContent = $dompdf->output();

                    $email = (new TemplatedEmail())
                        ->from(new Address('rimaffi65@gmail.com', 'Ardhi - Gestion Agricole'))
                        ->to(new Address($userEmail))
                        ->subject('Confirmation de votre maintenance planifiée - Ardhi')
                        ->htmlTemplate('MaterielEtMaintenance/maintenance/email_confirmation.html.twig')
                        ->context([
                            'maintenance' => $maintenance,
                            'user'        => $user,
                        ])
                        ->attach($pdfContent, 'confirmation-maintenance.pdf', 'application/pdf');

                    $mailer->send($email);
                }
            } catch (\Exception $e) {
                // Email non bloquant, mais on affiche l'erreur pour débugger
                $this->addFlash('danger', 'Erreur lors de l\'envoi de l\'email : ' . $e->getMessage());
            }
            // --- Fin email ---

            // Notification 1 : Planification + Google Calendar (si connecté)
            $isGoogleSync = false;
            if ($maintenance->getDateMaintenance() && $this->getUser()->getGoogleAccessToken()) {
                try {
                    $eventData = $googleCalendar->createMaintenanceEvent(
                        $this->getUser(),
                        $maintenance->getMateriel()->getNom(),
                        $maintenance->getDescription() ?? 'Intervention planifiée.',
                        $maintenance->getDateMaintenance()
                    );

                    if ($eventData && isset($eventData['id'])) {
                        $maintenance->setGoogleCalendarEventId($eventData['id']);
                        $em->flush();
                        $this->addFlash('success', sprintf(
                            'Maintenance planifiée et ajoutée à Google Calendar ! <a href="%s" target="_blank" class="btn btn-sm btn-light rounded-pill ms-3 shadow-sm" style="color: #2e7d32; font-weight: 600; border: 1px solid #c3e6cb; display: inline-flex; align-items: center; gap: 5px;"><i class="bi bi-calendar-check"></i> Voir l\'événement</a>',
                            $eventData['link']
                        ));
                        $isGoogleSync = true;
                    }
                } catch (\Exception $e) {
                    // Erreur Google Calendar non fatale
                }
            }

            if (!$isGoogleSync) {
                $this->addFlash('success', 'Maintenance planifiée !');
            }

            // Notification 2 : Email
            $userEmail = method_exists($this->getUser(), 'getEmail') ? $this->getUser()->getEmail() : null;
            if ($userEmail) {
                $this->addFlash('success', sprintf(
                    'Un email de confirmation est envoyé à <strong>%s</strong>',
                    $userEmail
                ));
            }

            return $this->redirectToRoute('app_maintenance_show', ['id' => $maintenance->getIdMaintenance()]);
        }

        return $this->render('MaterielEtMaintenance/maintenance/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Maintenance $maintenance): Response
    {
        return $this->render('MaterielEtMaintenance/maintenance/show.html.twig', [
            'maintenance' => $maintenance,
        ]);
    }

    #[Route('/{id}/modifier', name: 'edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, MaintenanceRepository $repo, EntityManagerInterface $em, GoogleCalendarService $googleCalendar): Response
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
                $materiel->calculerProchaineMaintenance();
            }

            $em->flush();

            // Synchro Google Calendar
            $isGoogleSync = false;
            if ($maintenance->getDateMaintenance() && $this->getUser()->getGoogleAccessToken()) {
                try {
                    $eventId = $maintenance->getGoogleCalendarEventId();
                    if ($eventId) {
                        $eventData = $googleCalendar->updateMaintenanceEvent(
                            $this->getUser(),
                            $eventId,
                            $maintenance->getMateriel()->getNom(),
                            $maintenance->getDescription() ?? 'Intervention planifiée.',
                            $maintenance->getDateMaintenance()
                        );
                    } else {
                        $eventData = $googleCalendar->createMaintenanceEvent(
                            $this->getUser(),
                            $maintenance->getMateriel()->getNom(),
                            $maintenance->getDescription() ?? 'Intervention planifiée.',
                            $maintenance->getDateMaintenance()
                        );
                    }
                    
                    if ($eventData && isset($eventData['id'])) {
                        if ($eventId !== $eventData['id']) {
                            $maintenance->setGoogleCalendarEventId($eventData['id']);
                            $em->flush();
                        }
                        $this->addFlash('success', sprintf(
                            'Maintenance mise à jour et synchronisée avec Google Calendar ! <a href="%s" target="_blank" class="btn btn-sm btn-light rounded-pill ms-3 shadow-sm" style="color: #2e7d32; font-weight: 600; border: 1px solid #c3e6cb; display: inline-flex; align-items: center; gap: 5px;"><i class="bi bi-calendar-check"></i> Voir l\'événement</a>',
                            $eventData['link']
                        ));
                        $isGoogleSync = true;
                    }
                } catch (\Exception $e) {
                }
            }

            if (!$isGoogleSync) {
                $this->addFlash('success', 'Maintenance mise à jour avec succès !');
            }

            return $this->redirectToRoute('app_maintenance_index');
        }

        return $this->render('MaterielEtMaintenance/maintenance/edit.html.twig', [
            'form' => $form->createView(),
            'maintenance' => $maintenance,
        ]);
    }

    #[Route('/{id}/supprimer', name: 'delete', methods: ['POST'])]
    public function delete(int $id, Request $request, MaintenanceRepository $repo, EntityManagerInterface $em, GoogleCalendarService $googleCalendar): Response
    {
        $maintenance = $this->getMaintenanceOwnedByUser($id, $repo);

        if ($this->isCsrfTokenValid('delete_maintenance_' . $id, $request->request->get('_token'))) {
            // Suppression sur Google Calendar
            $eventId = $maintenance->getGoogleCalendarEventId();
            if ($eventId && $this->getUser()->getGoogleAccessToken()) {
                $googleCalendar->deleteMaintenanceEvent($this->getUser(), $eventId);
            }

            $materiel = $maintenance->getMateriel();

            $em->remove($maintenance);
            $em->flush();

            // Recalculer la prochaine maintenance depuis la dernière stockée
            $dernier = $repo->findOneBy(['materiel' => $materiel, 'statut_maintenance' => 'terminee'], ['date_maintenance' => 'DESC']);
            if ($dernier) {
                $dateCible = method_exists($dernier, 'getDateRealisee') && $dernier->getDateRealisee() ? $dernier->getDateRealisee() : $dernier->getDateMaintenance();
                $materiel->setDerniereMaintenance($dateCible);
            } else {
                $materiel->setDerniereMaintenance(null);
            }
            $materiel->calculerProchaineMaintenance();
            
            $em->flush();
            
            $this->addFlash('success', 'Maintenance supprimée (et retirée de Google Calendar).');
        } else {
            $this->addFlash('danger', 'Action non autorisée.');
        }

        return $this->redirectToRoute('app_maintenance_index');
    }

    private function getMaintenanceOwnedByUser(int $id, MaintenanceRepository $repo): Maintenance
    {
        $maintenance = $repo->find($id);
        if (!$maintenance || $maintenance->getMateriel()->getUser()?->getId() !== $this->getUser()->getId()) {
            throw $this->createNotFoundException('Maintenance introuvable.');
        }
        return $maintenance;
    }
}
