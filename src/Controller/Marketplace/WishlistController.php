<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Produits;
use App\Entity\Marketplace\Wishlist;
use App\Repository\Marketplace\ProduitsRepository;
use App\Repository\Marketplace\WishlistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/marketplace/favoris')]
#[IsGranted('ROLE_USER')]
class WishlistController extends AbstractController
{
    #[Route('/', name: 'app_marketplace_favoris')]
    public function index(WishlistRepository $wishlistRepository): Response
    {
        $user = $this->getUser();
        $favorites = $wishlistRepository->findBy(['user' => $user], ['dateAjout' => 'DESC']);

        return $this->render('Marketplace/favoris.html.twig', [
            'favorites' => $favorites,
        ]);
    }

    #[Route('/toggle/{id}', name: 'app_marketplace_wishlist_toggle', methods: ['POST'])]
    public function toggle(
        Produits $produit, 
        WishlistRepository $wishlistRepository, 
        EntityManagerInterface $em
    ): JsonResponse {
        $user = $this->getUser();
        
        $wishlistItem = $wishlistRepository->findOneBy([
            'user' => $user,
            'produit' => $produit
        ]);

        if ($wishlistItem) {
            $em->remove($wishlistItem);
            $em->flush();
            return new JsonResponse([
                'success' => true,
                'isFavorite' => false,
                'message' => 'Produit retiré de vos favoris.'
            ]);
        }

        $wishlistItem = new Wishlist();
        $wishlistItem->setUser($user);
        $wishlistItem->setProduit($produit);
        
        $em->persist($wishlistItem);
        $em->flush();

        return new JsonResponse([
            'success' => true,
            'isFavorite' => true,
            'message' => 'Produit ajouté à vos favoris !'
        ]);
    }
}
