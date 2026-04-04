<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\CommandeRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Contrôleur dédié à la gestion des commandes Marketplace.
 */
class CommandeController extends AbstractController
{
    /**
     * Page "Mes Commandes" — Vue acheteur.
     */
    #[Route('/marketplace/mes-commandes', name: 'app_marketplace_mes_commandes')]
    public function mesCommandes(CommandeRepository $commandeRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        $commandes = $commandeRepo->findByUser($user);
        $stats     = $commandeRepo->getStatsForBuyer($user);

        return $this->render('Marketplace/mes_commandes.html.twig', [
            'commandes' => $commandes,
            'stats'     => $stats,
        ]);
    }

    /**
     * Page "Commandes Reçues" — Vue vendeur.
     */
    #[Route('/marketplace/commandes-recues', name: 'app_marketplace_commandes_recues')]
    public function commandesRecues(CommandeRepository $commandeRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        $commandes = $commandeRepo->findOrdersBySeller($user);
        $stats     = $commandeRepo->getStatsForSeller($user);

        return $this->render('Marketplace/commandes_recues.html.twig', [
            'commandes' => $commandes,
            'stats'     => $stats,
        ]);
    }

    /**
     * Détails d'une commande (AJAX) — retourne du JSON.
     */
    #[Route('/marketplace/commande/{id}/details', name: 'app_marketplace_commande_details', methods: ['GET'])]
    public function detailsCommande(int $id, CommandeRepository $commandeRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $commande = $commandeRepo->findCommandeWithDetails($id);
        if (!$commande) {
            return $this->json(['success' => false, 'message' => 'Commande introuvable.'], 404);
        }

        // Vérifier que l'utilisateur est bien l'acheteur ou un vendeur concerné
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $isOwner = $commande->getUser()->getId() === $user->getId();
        $isSeller = false;
        foreach ($commande->getDetails() as $detail) {
            if ($detail->getProduit() && $detail->getProduit()->getUser() && $detail->getProduit()->getUser()->getId() === $user->getId()) {
                $isSeller = true;
                break;
            }
        }

        if (!$isOwner && !$isSeller) {
            return $this->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $items = [];
        foreach ($commande->getDetails() as $detail) {
            $produit = $detail->getProduit();
            $items[] = [
                'nom'          => $produit ? $produit->getNom() : 'Produit supprimé',
                'image'        => $produit ? $produit->getImage() : null,
                'quantite'     => $detail->getQuantite(),
                'prixUnitaire' => number_format($detail->getPrixUnitaire(), 2, ',', ' '),
                'sousTotal'    => number_format($detail->getSousTotal(), 2, ',', ' '),
                'vendeur'      => $produit && $produit->getUser() ? $produit->getUser()->getNom() . ' ' . $produit->getUser()->getPrenom() : '—',
            ];
        }

        return $this->json([
            'success' => true,
            'commande' => [
                'id'    => $commande->getId(),
                'date'  => $commande->getDateCommande()->format('d/m/Y'),
                'etat'  => $commande->getEtat(),
                'total' => number_format($commande->getTotal(), 2, ',', ' '),
                'items' => $items,
            ],
        ]);
    }

    /**
     * Mettre à jour l'état d'une commande (vendeur uniquement).
     */
    #[Route('/marketplace/commande/{id}/status/{status}', name: 'app_marketplace_commande_update_status', methods: ['POST'])]
    public function updateStatus(int $id, string $status, CommandeRepository $commandeRepo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $validStatuses = ['en_attente', 'en_cours', 'livree', 'annulee'];
        if (!in_array($status, $validStatuses)) {
            return $this->json(['success' => false, 'message' => 'État invalide.'], 400);
        }

        $commande = $commandeRepo->find($id);
        if (!$commande) {
            return $this->json(['success' => false, 'message' => 'Commande introuvable.'], 404);
        }

        // Vérifier que l'utilisateur est vendeur de cette commande
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $isSeller = false;
        foreach ($commande->getDetails() as $detail) {
            if ($detail->getProduit() && $detail->getProduit()->getUser() && $detail->getProduit()->getUser()->getId() === $user->getId()) {
                $isSeller = true;
                break;
            }
        }

        if (!$isSeller) {
            return $this->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $commande->setEtat($status);
        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'État mis à jour avec succès.',
            'etat'    => $status,
        ]);
    }
}
