<?php

namespace App\Controller\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Reclamation;
use App\Form\MaterielEtMaintenance\ReclamationType;
use App\Repository\MaterielEtMaintenance\MaterielRepository;
use App\Repository\MaterielEtMaintenance\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/materiel-et-maintenance/reclamation', name: 'app_reclamation_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class ReclamationController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(ReclamationRepository $repo): Response
    {
        $reclamations = $repo->findBy(
            ['user' => $this->getUser()],
            ['createdAt' => 'DESC']
        );

        return $this->render('MaterielEtMaintenance/reclamation/index.html.twig', [
            'reclamations' => $reclamations,
        ]);
    }

    #[Route('/nouveau/{materielId}', name: 'new', methods: ['GET', 'POST'])]
    public function new(int $materielId, Request $request, MaterielRepository $matRepo, EntityManagerInterface $em): Response
    {
        $materiel = $matRepo->find($materielId);

        if (!$materiel || $materiel->getUser()?->getId() !== $this->getUser()->getId()) {
            throw $this->createNotFoundException('Matériel introuvable.');
        }

        $reclamation = new Reclamation();
        $reclamation->setUser($this->getUser());
        $reclamation->setMateriel($materiel);
        $reclamation->setSujet('Retard de maintenance - ' . $materiel->getNom());

        $form = $this->createForm(ReclamationType::class, $reclamation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($reclamation);

            // Notification à l'admin (on cherche tous les admins)
            $adminUsers = $em->getRepository(\App\Entity\UserAndDiag\User::class)
                ->createQueryBuilder('u')
                ->where('u.role = :role')
                ->setParameter('role', 'ADMIN')
                ->getQuery()
                ->getResult();

            foreach ($adminUsers as $admin) {
                $notif = new \App\Entity\MaterielEtMaintenance\NotificationMaintenance();
                $notif->setUser($admin);
                $notif->setMateriel($materiel);
                $notif->setNouveauStatut('reclamation_soumise');
                $em->persist($notif);
            }

            $em->flush();

            $this->addFlash('success', 'Votre réclamation a été soumise avec succès. L\'administrateur en sera notifié.');
            return $this->redirectToRoute('app_reclamation_index');
        }

        return $this->render('MaterielEtMaintenance/reclamation/new.html.twig', [
            'form' => $form->createView(),
            'materiel' => $materiel,
        ]);
    }
}
