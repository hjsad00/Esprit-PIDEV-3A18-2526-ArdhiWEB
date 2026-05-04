<?php

namespace App\Controller\Marketplace\Admin;

use App\Repository\Marketplace\CommandeRepository;
use App\Repository\UserAndDiag\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/marketplace/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminCommandeController extends AbstractController
{
    #[Route('/commandes', name: 'admin_marketplace_commandes')]
    public function commandes(Request $request, CommandeRepository $commandeRepo, UserRepository $userRepo): Response
    {
        $filters = [
            'id'         => $request->query->get('id', ''),
            'client'     => $request->query->get('client', ''),
            'vendeur'    => $request->query->get('vendeur', ''),
            'status'     => $request->query->get('status', ''),
            'mode'       => $request->query->get('mode', ''),
            'date_debut' => $request->query->get('date_debut', ''),
            'date_fin'   => $request->query->get('date_fin', ''),
        ];

        // --- VALIDATION SERVEUR (PHP) ---
        if ($filters['date_debut'] !== '' && $filters['date_fin'] !== '') {
            if ($filters['date_fin'] < $filters['date_debut']) {
                // Inversion si dates incohérentes
                $temp = $filters['date_debut'];
                $filters['date_debut'] = $filters['date_fin'];
                $filters['date_fin'] = $temp;
            }
        }

        // 1. Récupérer les commandes filtrées
        $commandes = $commandeRepo->findAllWithFilters($filters);

        // 2. Récupérer la liste des clients et vendeurs potentiels (exclure les admins)
        $users = $userRepo->createQueryBuilder('u')
            ->where('u.role != :admin')
            ->setParameter('admin', 'ADMIN')
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('Marketplace/admin/commandes.html.twig', [
            'commandes' => $commandes,
            'clients' => $users,
            'vendeurs' => $users,
            'filters' => $filters
        ]);
    }
}
