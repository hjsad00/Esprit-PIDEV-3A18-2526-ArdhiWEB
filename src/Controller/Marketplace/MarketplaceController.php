<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\ProduitsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur de la page d'accueil du marketplace.
 */
class MarketplaceController extends AbstractController
{
    #[Route('/marketplace', name: 'app_marketplace')]
    public function index(ProduitsRepository $produitsRepository): Response
    {
        $produits = $produitsRepository->findAll();

        return $this->render('Marketplace/accueil.html.twig', [
            'produits' => $produits,
        ]);
    }
}