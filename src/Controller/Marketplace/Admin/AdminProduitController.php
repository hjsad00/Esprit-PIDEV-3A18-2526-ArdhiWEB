<?php

namespace App\Controller\Marketplace\Admin;

use App\Entity\Marketplace\Produits;
use App\Repository\Marketplace\ProduitsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;

#[Route('/marketplace/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminProduitController extends AbstractController
{
    #[Route('/produits', name: 'admin_marketplace_produits')]
    public function produits(ProduitsRepository $repo): Response
    {
        $produits = $repo->findBy([], ['id' => 'DESC']);
        
        return $this->render('Marketplace/admin/produits.html.twig', [
            'produits' => $produits
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
