<?php

namespace App\Controller\Admin;

use App\Entity\MaterielEtMaintenance\Maintenance;
use App\Repository\MaterielEtMaintenance\MaintenanceRepository;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Workflow\Registry;

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

                // Notification pour le propriétaire du matériel avec message automatique
                $materiel = $maintenance->getMateriel();
                if ($materiel) {
                    $userId = $materiel->getUserId();
                    $user = $userRepo->find($userId);
                    if ($user) {
                        $notif = new \App\Entity\MaterielEtMaintenance\NotificationMaintenance();
                        $notif->setUser($user);
                        $notif->setMateriel($materiel);
                        $notif->setNouveauStatut($nouveauStatut);
                        
                        // Logique de message selon le nouveau statut
                        $msg = "Le statut de votre matériel a été mis à jour : " . ucfirst($nouveauStatut);
                        $reponseType = $request->request->get('reponse_type');

                        switch($nouveauStatut) {
                            case 'planifiee':
                                $msg = "Votre maintenance a été planifiée. Consultez le calendrier pour les détails.";
                                break;
                            case 'en_attente':
                                if ($reponseType === 'urgent_apportez') {
                                    $msg = "Vous avez accepté la maintenance en urgence de l'agriculteur";
                                } else {
                                    $msg = "Une demande de plannification est envoyer a l'agriculteur";
                                }
                                break;
                            case 'en_cours':
                                if ($reponseType === 'non_urgent_planifier') {
                                    $msg = "Action requise : Veuillez planifier un créneau pour votre maintenance via le calendrier.";
                                } else {
                                    $msg = "L'intervention sur votre matériel a officiellement commencé.";
                                }
                                break;
                            case 'terminee':
                                $msg = "Maintenance terminée avec succès. Votre matériel est de nouveau opérationnel et prêt à l'emploi.";
                                break;
                            case 'annulee':
                                $msg = "L'intervention prévue sur votre matériel a été annulée. Contactez l'administration pour plus d'infos.";
                                break;
                            case 'verifie':
                                $msg = "L'intervention a été effectuée et est en cours de vérification par nos techniciens.";
                                break;
                        }
                        
                        $notif->setMessage($msg);
                        $notif->setTitre("Mise à jour : " . $materiel->getNom() . " (" . ucfirst($nouveauStatut) . ")");
                        
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

    #[Route('/urgente', name: 'urgente', methods: ['GET'])]
    public function urgente(MaintenanceRepository $repo, UserRepository $userRepo, \App\Repository\MaterielEtMaintenance\MaterielRepository $matRepo): Response
    {
        $urgencies = $repo->findBy(['type_maintenance' => 'urgente'], ['date_maintenance' => 'DESC']);
        
        // On garde tout pour afficher soit les boutons soit le message de décision
        $users = $userRepo->findAll();
        $userMap = [];
        foreach ($users as $u) {
            $userMap[$u->getId()] = $u;
        }

        return $this->render('admin/maintenance/urgente.html.twig', [
            'urgencies' => $urgencies,
            'userMap' => $userMap,
        ]);
    }

    #[Route('/{id}/decide', name: 'decide', methods: ['POST'])]
    public function decide(
        int $id, 
        Request $request, 
        MaintenanceRepository $repo, 
        EntityManagerInterface $em, 
        UserRepository $userRepo,
        \App\Service\MaterielEtMaintenance\MaintenanceMailer $mailer,
        \App\Service\MaterielEtMaintenance\WhatsAppService $whatsApp,
        \Symfony\Component\Workflow\Registry $workflowRegistry,
        LoggerInterface $logger
    ): Response {
        $maintenance = $repo->find($id);

        if (!$maintenance) {
            $this->addFlash('danger', 'Maintenance introuvable.');
            return $this->redirectToRoute('admin_maintenance_urgente');
        }

        // Vérification de l'immutabilité (Décision définitive)
        if ($maintenance->getDecisionAdmin() !== null) {
            $this->addFlash('warning', 'Une décision a déjà été prise pour cette demande.');
            return $this->redirectToRoute('admin_maintenance_urgente');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('decide_'.$maintenance->getIdMaintenance(), $token)) {
            $this->addFlash('danger', 'Token de sécurité invalide.');
            return $this->redirectToRoute('admin_maintenance_urgente');
        }

        $type = $request->request->get('reponse_type');
        $materiel = $maintenance->getMateriel();
        $machineName = $materiel ? $materiel->getNom() : 'votre machine';
        
        $userId = $materiel ? $materiel->getUserId() : null;
        $user = $userId ? $userRepo->find($userId) : null;

        if (!$user) {
            $this->addFlash('danger', 'Propriétaire introuvable.');
            return $this->redirectToRoute('admin_maintenance_urgente');
        }

        $notif = new \App\Entity\MaterielEtMaintenance\NotificationMaintenance();
        $notif->setUser($user);
        $notif->setMateriel($materiel);

        // Récupération du Workflow via le registre pour être 100% sûr
        $workflow = $workflowRegistry->get($materiel, 'materiel_lifecycle');

        if ($type === 'urgent_apportez') {
            $maintenance->setDecisionAdmin('urgent_accepte');
            $maintenance->setStatutMaintenance('en_attente');
            
            // On force le statut et l'état
            if ($workflow->can($materiel, 'valider_maintenance')) {
                $workflow->apply($materiel, 'valider_maintenance');
            } else {
                // Secours : si la transition est bloquée, on force manuellement la propriété
                $materiel->setStatut('en_maintenance');
            }
            $materiel->setEtat('En maintenance');

            $msgNotif = "Le responsable a accepté votre demande de maintenance urgente pour votre machine " . $machineName . ", veuillez apporter votre matériel dès que possible";
            $msgFlash = "Vous avez accepté la maintenance en urgence de l'agriculteur.";
            
            $notif->setMessage($msgNotif);
            $notif->setTitre("Urgence Acceptée : " . $machineName);
            $notif->setNouveauStatut('en_attente');

            // --- Envoi E-mail ---
            $mailer->sendUrgentAcceptedEmail($user->getEmail(), ($user->getPrenom() . ' ' . $user->getNom()));

        } elseif ($type === 'non_urgent_planifier') {
            $maintenance->setDecisionAdmin('planification_demandee');
            $maintenance->setStatutMaintenance('en_cours');
            
            // On force le statut (Attente Planification)
            if ($workflow->can($materiel, 'demander_planification')) {
                $workflow->apply($materiel, 'demander_planification');
            } else {
                $materiel->setStatut('attente_planification');
            }
            $materiel->setEtat('En panne');
            
            $msgNotif = "Votre demande de maintenance pour votre machine " . $machineName . " a été reçue, veuillez planifier une intervention via la page de maintenance";
            $msgFlash = "Une demande de plannification a été envoyée à l'agriculteur.";
            
            $notif->setMessage($msgNotif);
            $notif->setTitre("Planification demandée : " . $machineName);
            $notif->setNouveauStatut('en_cours');

            // --- Envoi E-mail ---
            $mailer->sendPlanificationRequestedEmail($user->getEmail(), ($user->getPrenom() . ' ' . $user->getNom()));

        } else {
            $this->addFlash('danger', 'Type de réponse invalide.');
            return $this->redirectToRoute('admin_maintenance_urgente');
        }

        // --- Envoi WhatsApp commun (urgent + non urgent) ---
        $phone = $user->getPhone();
        if (!empty($phone)) {
            $whatsApp->envoyer($phone, $msgNotif);
        } else {
            $logger->warning('Envoi WhatsApp ignoré: numéro manquant pour le propriétaire du matériel.', [
                'maintenance_id' => $maintenance->getIdMaintenance(),
                'reponse_type' => $type,
                'user_id' => $user->getId(),
            ]);
        }

        // On persiste et on flush tout explicitement
        $em->persist($notif);
        if ($materiel) {
            $em->persist($materiel);
        }
        $em->persist($maintenance);
        $em->flush();

        $this->addFlash('success', $msgFlash);

        return $this->redirectToRoute('admin_maintenance_urgente');
    }
}
