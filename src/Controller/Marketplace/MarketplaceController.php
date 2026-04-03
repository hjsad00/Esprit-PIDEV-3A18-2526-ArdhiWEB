<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\ProduitsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class MarketplaceController extends AbstractController
{
    #[Route('/marketplace', name: 'app_marketplace')]
    public function index(ProduitsRepository $produitsRepository): Response
    {
        $produits = $produitsRepository->findAll();

        return $this->render('Marketplace/index.html.twig', [
            'produits' => $produits,
        ]);
    }

    #[Route('/marketplace/catalogue', name: 'app_marketplace_catalogue')]
    public function catalogue(ProduitsRepository $produitsRepository): Response
    {
        $user = $this->getUser();
        $produits = $produitsRepository->findAllExceptUser($user ? $user->getId() : null);

        return $this->render('Marketplace/catalogue.html.twig', [
            'produits' => $produits,
        ]);
    }

    #[Route('/marketplace/produit/{id}', name: 'app_marketplace_produit_show')]
    public function show(int $id, ProduitsRepository $produitsRepository): Response
    {
        $produit = $produitsRepository->find($id);

        if (!$produit) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        return $this->render('Marketplace/details.html.twig', [
            'produit' => $produit,
        ]);
    }
}
