<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\PanierProduitRepository;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur dédié à la gestion du panier d'achat.
 */
class PanierController extends AbstractController
{
    #[Route('/marketplace/panier', name: 'app_marketplace_panier')]
    public function panier(PanierRepository $panierRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $panierId = $panierRepository->getOrCreatePanier($user)->getId();
        $panier = $panierRepository->findPanierWithProduits($panierId);

        return $this->render('Marketplace/panier.html.twig', [
            'panier' => $panier,
        ]);
    }

    #[Route('/marketplace/panier/add/{id}', name: 'app_marketplace_panier_add', methods: ['POST'])]
    public function ajouterAuPanier(
        int $id,
        Request $request,
        ProduitsRepository $produitsRepository,
        PanierRepository $panierRepository,
        PanierProduitRepository $ppRepository,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $produit = $produitsRepository->find($id);

        if (!$produit || $produit->getUser()->getId() === $user->getId()) {
            $this->addFlash('danger', 'Ajout impossible : produit invalide ou vous appartenant.');
            return $this->redirectToRoute('app_marketplace_catalogue');
        }

        $quantite = (int) $request->request->get('quantite', 1);
        if ($quantite > $produit->getQuantiteStock()) {
            $this->addFlash('warning', 'Quantité demandée supérieure au stock disponible.');
            return $this->redirectToRoute('app_marketplace_catalogue');
        }

        $panier = $panierRepository->getOrCreatePanier($user);
        $ppRepository->ajouterOuIncrementer($panier, $produit, $quantite);

        $this->addFlash('success', 'Produit ajouté au panier !');
        return $this->redirectToRoute('app_marketplace_catalogue');
    }

    #[Route('/marketplace/panier/update/{id}', name: 'app_marketplace_panier_update', methods: ['POST'])]
    public function modifierQuantite(
        int $id,
        Request $request,
        PanierRepository $panierRepository,
        PanierProduitRepository $ppRepository,
        ProduitsRepository $produitsRepository
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $panier = $panierRepository->getOrCreatePanier($user);
        $produit = $produitsRepository->find($id);

        if (!$produit) {
            return $this->redirectToRoute('app_marketplace_panier');
        }

        $quantite = (int) $request->request->get('quantite');
        if ($quantite > $produit->getQuantiteStock()) {
            $this->addFlash('warning', 'Stock insuffisant pour ce produit.');
            return $this->redirectToRoute('app_marketplace_panier');
        }

        $ppRepository->modifierQuantite($panier, $produit, $quantite);

        $this->addFlash('success', 'Panier mis à jour.');
        return $this->redirectToRoute('app_marketplace_panier');
    }

    #[Route('/marketplace/panier/delete/{id}', name: 'app_marketplace_panier_delete', methods: ['POST'])]
    public function supprimerLigne(
        int $id,
        PanierRepository $panierRepository,
        PanierProduitRepository $ppRepository,
        ProduitsRepository $produitsRepository
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $panier = $panierRepository->getOrCreatePanier($user);
        $produit = $produitsRepository->find($id);

        if ($produit) {
            $ppRepository->supprimerLigne($panier, $produit);
            $this->addFlash('success', 'Produit retiré du panier.');
        }

        return $this->redirectToRoute('app_marketplace_panier');
    }

    #[Route('/marketplace/panier/clear', name: 'app_marketplace_panier_clear', methods: ['POST'])]
    public function viderPanier(PanierRepository $panierRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $panierRepository->viderPanierUser($user);

        $this->addFlash('success', 'Le panier a été vidé.');
        return $this->redirectToRoute('app_marketplace_panier');
    }
}
