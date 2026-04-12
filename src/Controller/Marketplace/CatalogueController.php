<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\AvisRepository;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\Marketplace\WishlistRepository;
use App\Service\Marketplace\CityCoordinatesService;

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
        AvisRepository $avisRepository,
        PanierRepository $panierRepository,
        WishlistRepository $wishlistRepository,
        CityCoordinatesService $cityCoordinatesService
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
            'lat'       => $request->query->get('lat', ''),
            'lng'       => $request->query->get('lng', ''),
            'radius'    => $request->query->get('radius', '25'),
            'location_name' => $request->query->get('location_name', ''),
        ];

        // --- FILTRAGE SPATIAL (VILLES PROCHES) ---
        if ($filters['lat'] !== '' && $filters['lng'] !== '') {
            $validCities = $cityCoordinatesService->getCitiesWithinRadius(
                (float)$filters['lat'],
                (float)$filters['lng'],
                (float)$filters['radius']
            );
            // Ajout du tableau des villes valides aux filtres
            // Si la liste est vide (aucune ville dans le rayon), on passe une valeur factice pour forcer 0 résultat ?
            // Ou on passe la liste vide à Doctrine.
            $filters['valid_cities'] = empty($validCities) ? ['__AUCUNE_VILLE__'] : $validCities;
        }

        // --- VALIDATION SERVEUR (PHP) ---
        // 1. Validation Prix
        if ($filters['prix_min'] !== '' && $filters['prix_max'] !== '') {
            $pMin = (float)$filters['prix_min'];
            $pMax = (float)$filters['prix_max'];
            if ($pMax < $pMin && $pMax > 0) {
                // Inversion si l'utilisateur a forcé des valeurs incohérentes dans l'URL
                $filters['prix_min'] = $pMax;
                $filters['prix_max'] = $pMin;
            }
        }

        // 2. Validation Stock
        if ($filters['stock_min'] !== '' && $filters['stock_max'] !== '') {
            $sMin = (int)$filters['stock_min'];
            $sMax = (int)$filters['stock_max'];
            if ($sMax < $sMin && $sMax > 0) {
                $filters['stock_min'] = $sMax;
                $filters['stock_max'] = $sMin;
            }
        }

        $produits    = $produitsRepository->findAllWithFilters($filters, $userId);

        $reviewsStats = $avisRepository->getStatsForProduits($produits);
        foreach ($produits as $produit) {
            $stats = $reviewsStats[$produit->getId()] ?? ['avg' => 0.0, 'count' => 0];
            $produit
                ->setAverageRating((float) $stats['avg'])
                ->setReviewsCount((int) $stats['count']);
        }

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
