<?php

namespace App\Controller\Marketplace\Admin;

use App\Repository\Marketplace\ReclamationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/marketplace/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminReclamationController extends AbstractController
{
    #[Route('/reclamations', name: 'admin_marketplace_reclamations', methods: ['GET'])]
    public function index(ReclamationRepository $reclamationRepo, Request $request): Response
    {
        // Récupérer toutes les réclamations
        $reclamations = $reclamationRepo->findBy([], ['dateReclamation' => 'DESC']);
        
        // Calcul des statistiques globales
        $stats = [
            'total'      => count($reclamations),
            'en_attente' => 0,
            'en_cours'   => 0,
            'resolue'    => 0,
            'rejetee'    => 0,
        ];
        foreach ($reclamations as $rec) {
            $s = strtolower($rec->getStatut());
            if (isset($stats[$s])) {
                $stats[$s]++;
            }
        }

        return $this->render('Marketplace/admin/reclamations.html.twig', [
            'reclamations' => $reclamations,
            'stats'        => $stats,
        ]);
    }

    #[Route('/reclamations/{id}/status/{newStatus}', name: 'admin_marketplace_reclamations_status', methods: ['POST'])]
    public function updateStatus(int $id, string $newStatus, ReclamationRepository $reclamationRepo, EntityManagerInterface $em): JsonResponse
    {
        $reclamation = $reclamationRepo->find($id);
        if (!$reclamation) {
            return $this->json(['success' => false, 'message' => 'Réclamation introuvable.']);
        }

        $validStatuses = ['EN_ATTENTE', 'EN_COURS', 'RESOLUE', 'REJETEE'];
        if (!in_array(strtoupper($newStatus), $validStatuses)) {
            return $this->json(['success' => false, 'message' => 'Statut invalide.']);
        }

        $reclamation->setStatut(strtoupper($newStatus));
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'Statut mis à jour avec succès.',
            'newStatus' => strtoupper($newStatus)
        ]);
    }
}
