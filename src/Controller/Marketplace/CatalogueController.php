<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\Marketplace\WishlistRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur de la page catalogue du marketplace.
 */
class CatalogueController extends AbstractController
{
    #[Route('/marketplace/catalogue', name: 'app_marketplace_catalogue')]
    public function catalogue(
        ProduitsRepository $produitsRepository, 
        PanierRepository $panierRepository,
        WishlistRepository $wishlistRepository
    ): Response
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $produits = $produitsRepository->findAllExceptUser($user ? $user->getId() : null);

        $panier = null;
        $favIds = [];
        if ($user) {
            $panier = $panierRepository->findPanierActif($user);
            $favIds = $wishlistRepository->findAllIdsByUser($user);
        }

        return $this->render('Marketplace/catalogue.html.twig', [
            'produits' => $produits,
            'panier' => $panier,
            'favIds' => $favIds,
        ]);
    }
}
