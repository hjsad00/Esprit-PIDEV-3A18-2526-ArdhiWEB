<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\ProduitsRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur de la page de détails (fiche) d'un produit du marketplace.
 */
class DetailsProduitController extends AbstractController
{
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
