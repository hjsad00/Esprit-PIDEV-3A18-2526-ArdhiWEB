<?php

namespace App\Controller\Marketplace;

use App\Repository\Marketplace\AvisRepository;
use App\Repository\Marketplace\ProduitsRepository;
use App\Service\Marketplace\MarketplaceQrService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur de la page de détails (fiche) d'un produit du marketplace.
 */
class DetailsProduitController extends AbstractController
{
    #[Route('/marketplace/produit/{id}', name: 'app_marketplace_produit_show')]
    public function show(
        int $id, 
        ProduitsRepository $produitsRepo, 
        AvisRepository $avisRepo,
        MarketplaceQrService $marketplaceQrService
    ): Response {
        $produit = $produitsRepo->find($id);

        if (!$produit) {
            throw $this->createNotFoundException('Produit introuvable.');
        }

        // Génération/Récupération du QR Code pour le produit
        $qrPath = $marketplaceQrService->generateForProduct($produit);

        // Récupération des avis avec les auteurs (Users)
        $avisList = $avisRepo->findByProduitWithUser($produit);
        
        // ... rest of calculations ...
        $nbAvis = count($avisList);
        $avgRating = $avisRepo->getAverageNote($id);
        
        $starsStats = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($avisList as $av) {
            $n = $av->getNote();
            if (isset($starsStats[$n])) { $starsStats[$n]++; }
        }

        return $this->render('Marketplace/details.html.twig', [
            'produit' => $produit,
            'avis' => $avisList,
            'avgRating' => $avgRating,
            'nbAvis' => $nbAvis,
            'starsStats' => $starsStats,
            'qrPath' => $qrPath
        ]);
    }
}
