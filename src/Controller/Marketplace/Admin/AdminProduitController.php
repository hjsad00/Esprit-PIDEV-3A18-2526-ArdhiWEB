<?php

namespace App\Controller\Marketplace\Admin;

use App\Entity\Marketplace\Produits;
use App\Repository\Marketplace\ProduitsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/marketplace/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminProduitController extends AbstractController
{
    #[Route('/produits', name: 'admin_marketplace_produits')]
    public function produits(Request $request, ProduitsRepository $repo): Response
    {
        $filters = [
            'nom'     => $request->query->get('nom', ''),
            'vendeur' => $request->query->get('vendeur', ''),
            'prix_min'=> $request->query->get('prix_min', ''),
            'prix_max'=> $request->query->get('prix_max', ''),
            'stock_min'=> $request->query->get('stock_min', ''),
            'stock_max'=> $request->query->get('stock_max', ''),
            'visible' => $request->query->get('visible', 'ALL'),
            'admin'   => $request->query->get('admin', 'ALL'),
        ];

        // --- VALIDATION SERVEUR (PHP) ---
        if ($filters['prix_min'] !== '' && $filters['prix_max'] !== '') {
            if ((float)$filters['prix_max'] < (float)$filters['prix_min'] && (float)$filters['prix_max'] > 0) {
                $temp = $filters['prix_min'];
                $filters['prix_min'] = $filters['prix_max'];
                $filters['prix_max'] = $temp;
            }
        }

        if ($filters['stock_min'] !== '' && $filters['stock_max'] !== '') {
            if ((int)$filters['stock_max'] < (int)$filters['stock_min'] && (int)$filters['stock_max'] > 0) {
                $temp = $filters['stock_min'];
                $filters['stock_min'] = $filters['stock_max'];
                $filters['stock_max'] = $temp;
            }
        }

        // Utilisation de la méthode générique avec le mode ADMIN actif
        $produits = $repo->findAllWithFilters($filters, null, true);
        $priceRange = $repo->findPriceRange(null, true);
        
        return $this->render('Marketplace/admin/produits.html.twig', [
            'produits'   => $produits,
            'filters'    => $filters,
            'priceRange' => $priceRange
        ]);
    }

    #[Route('/produit/toggle-visible-admin/{id}', name: 'admin_marketplace_produit_toggle', methods: ['POST'])]
    public function toggleVisibleAdmin(Produits $produit, EntityManagerInterface $em): JsonResponse
    {
        $produit->setVisibleAdmin(!$produit->isVisibleAdmin());
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'newStatus' => $produit->isVisibleAdmin(),
            'message' => $produit->isVisibleAdmin() ? 'Produit autorisé par l\'admin.' : 'Produit bloqué par l\'admin.'
        ]);
    }
}
