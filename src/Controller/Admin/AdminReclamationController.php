<?php

namespace App\Controller\Admin;

use App\Entity\MaterielEtMaintenance\NotificationMaintenance;
use App\Repository\MaterielEtMaintenance\ReclamationRepository;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/reclamation', name: 'admin_reclamation_')]
#[IsGranted('ROLE_ADMIN')]
class AdminReclamationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ReclamationRepository $repo): Response
    {
        $reclamations = $repo->findAllOrderedByUrgenceAndDate();

        return $this->render('admin/reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/{id}/traiter', name: 'traiter', methods: ['POST'])]
    public function traiter(int $id, Request $request, ReclamationRepository $repo, EntityManagerInterface $em): Response
    {
        $reclamation = $repo->find($id);

        if (!$reclamation) {
            throw $this->createNotFoundException('Réclamation introuvable.');
        }

        if ($this->isCsrfTokenValid('traiter_reclamation_' . $id, $request->request->get('_token'))) {
            $nouveauStatut = $request->request->get('statut');
            $commentaire   = $request->request->get('commentaire_admin');

            $allowedStatuts = ['en_attente', 'en_cours', 'resolue'];
            if (in_array($nouveauStatut, $allowedStatuts)) {
                $reclamation->setStatut($nouveauStatut);
            }

            if (!empty(trim($commentaire ?? ''))) {
                $reclamation->setCommentaireAdmin($commentaire);
            }

            // Notification à l'agriculteur
            $notif = new NotificationMaintenance();
            $notif->setUser($reclamation->getUser());
            $notif->setMateriel($reclamation->getMateriel());
            $notif->setNouveauStatut('reclamation_' . $nouveauStatut);
            $em->persist($notif);

            $em->flush();
            $this->addFlash('success', 'Réclamation mise à jour et agriculteur notifié.');
        }

        return $this->redirectToRoute('admin_reclamation_index');
    }
}
