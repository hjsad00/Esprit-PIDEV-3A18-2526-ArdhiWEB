<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur de la page catalogue du marketplace.
 */
class CatalogueController extends AbstractController
{
    #[Route('/marketplace/catalogue', name: 'app_marketplace_catalogue')]
    public function catalogue(ProduitsRepository $produitsRepository, PanierRepository $panierRepository): Response
    {
        $user = $this->getUser();
        $produits = $produitsRepository->findAllExceptUser($user ? $user->getId() : null);

        $panier = null;
        if ($user) {
            $panier = $panierRepository->findPanierActif($user);
        }

        return $this->render('Marketplace/catalogue.html.twig', [
            'produits' => $produits,
            'panier' => $panier,
        ]);
    }
}
