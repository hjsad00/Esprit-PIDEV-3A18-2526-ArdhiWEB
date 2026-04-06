<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\Marketplace\WishlistRepository;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur de la page catalogue du marketplace.
 */
class CatalogueController extends AbstractController
{
    #[Route('/marketplace/catalogue', name: 'app_marketplace_catalogue')]
    public function catalogue(
        Request $request,
        ProduitsRepository $produitsRepository,
        PanierRepository $panierRepository,
        WishlistRepository $wishlistRepository
    ): Response
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user   = $this->getUser();
        $userId = $user ? $user->getId() : null;

        // Récupération des filtres depuis l'URL (GET)
        $filters = [
            'nom'       => $request->query->get('nom', ''),
            'categorie' => $request->query->get('categorie', ''),
            'prix_min'  => $request->query->get('prix_min', ''),
            'prix_max'  => $request->query->get('prix_max', ''),
            'stock_min' => $request->query->get('stock_min', ''),
            'stock_max' => $request->query->get('stock_max', ''),
            'en_solde'  => $request->query->get('en_solde', ''),
            'tri'       => $request->query->get('tri', 'recent'),
        ];

        $produits    = $produitsRepository->findAllWithFilters($filters, $userId);
        $categories  = $produitsRepository->findDistinctCategories();
        $priceRange  = $produitsRepository->findPriceRange($userId);

        $panier = null;
        $favIds = [];
        if ($user) {
            $panier = $panierRepository->findPanierActif($user);
            $favIds = $wishlistRepository->findAllIdsByUser($user);
        }

        return $this->render('Marketplace/catalogue.html.twig', [
            'produits'   => $produits,
            'panier'     => $panier,
            'favIds'     => $favIds,
            'filters'    => $filters,
            'categories' => $categories,
            'priceRange' => $priceRange,
        ]);
    }
}
