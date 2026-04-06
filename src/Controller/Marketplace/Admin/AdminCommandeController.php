<?php

namespace App\Controller\Marketplace\Admin;

use App\Repository\Marketplace\CommandeRepository;
use App\Repository\UserAndDiag\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/marketplace/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminCommandeController extends AbstractController
{
    #[Route('/commandes', name: 'admin_marketplace_commandes')]
    public function commandes(CommandeRepository $commandeRepo, UserRepository $userRepo): Response
    {
        // 1. Récupérer toutes les commandes (triées par date décroissante)
        $commandes = $commandeRepo->findBy([], ['dateCommande' => 'DESC', 'id' => 'DESC']);

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
            'vendeurs' => $users
        ]);
    }
}
