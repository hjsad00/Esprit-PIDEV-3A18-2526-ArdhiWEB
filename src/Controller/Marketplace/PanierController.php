<?php

namespace App\Controller\Marketplace;

use App\Entity\UserAndDiag\User;
use App\Repository\Marketplace\PanierProduitRepository;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\ProduitsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
/**
 * Contrôleur dédié à la gestion du panier d'achat.
 */
class PanierController extends AbstractController
{
    #[Route('/marketplace/panier/summary', name: 'app_marketplace_panier_summary', methods: ['GET'])]
    public function summary(PanierRepository $panierRepository): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);
        $panier = $panierRepository->findPanierActif($user);

        if (!$panier) {
            $response = $this->json([
                'success' => true,
                'count' => 0,
                'total' => '0.00',
            ]);
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            return $response;
        }

        $panierFrais = $panierRepository->findPanierWithProduits((int) $panier->getId()) ?? $panier;

        $response = $this->json([
            'success' => true,
            'count' => $panierFrais->getTotalProduits(),
            'total' => number_format($panierFrais->getTotalMontant(), 2, '.', ' '),
        ]);
        $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
        $response->headers->set('Pragma', 'no-cache');
        return $response;
    }

    #[Route('/marketplace/panier', name: 'app_marketplace_panier')]
    public function panier(
        PanierRepository $panierRepository,
        #[Autowire('%karser_recaptcha3.site_key%')] string $recaptchaSiteKey
    ): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);
        $panierId = (int) $panierRepository->getOrCreatePanier($user)->getId();
        $panier = $panierRepository->findPanierWithProduits($panierId) ?? $panierRepository->getOrCreatePanier($user);

        return $this->render('Marketplace/panier.html.twig', [
            'panier' => $panier,
            'recaptchaSiteKey' => $recaptchaSiteKey,
        ]);
    }

#[Route('/marketplace/panier/add/{id}', name: 'app_marketplace_panier_add', methods: ['POST'])]
public function ajouterAuPanier(
    int $id,
    Request $request,
    ProduitsRepository $produitsRepository,
    PanierRepository $panierRepository,
    PanierProduitRepository $ppRepository,
    EntityManagerInterface $em
): Response {
    $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

    $user    = $this->getUser();
        assert($user instanceof User);
    $produit = $produitsRepository->find($id);
    $isAjax  = $request->headers->get('X-Requested-With') === 'XMLHttpRequest'
               || $request->headers->get('Accept') === 'application/json';

    if (!$produit || !$produit->getUser() || $produit->getUser()->getId() === $user->getId()) {
        if ($isAjax) {
            return $this->json(['success' => false, 'message' => 'Ajout impossible.'], 400);
        }
        $this->addFlash('danger', 'Ajout impossible : produit invalide ou vous appartenant.');
        return $this->redirectToRoute('app_marketplace_catalogue');
    }

    $quantite = (int) $request->request->get('quantite', 1);
    if ($quantite > $produit->getQuantiteStock()) {
        if ($isAjax) {
            return $this->json(['success' => false, 'message' => 'Stock insuffisant.'], 400);
        }
        $this->addFlash('warning', 'Quantité demandée supérieure au stock disponible.');
        return $this->redirectToRoute('app_marketplace_catalogue');
    }

    $panier = $panierRepository->getOrCreatePanier($user);
    $ppRepository->ajouterOuIncrementer($panier, $produit, $quantite);

    // Recharger le panier pour avoir les totaux à jour
    $panierFrais = $panierRepository->findPanierWithProduits((int) $panier->getId()) ?? $panier;

    if ($isAjax) {
        return $this->json([
            'success'    => true,
            'message'    => 'Produit ajouté au panier !',
            'cartCount'  => $panierFrais->getTotalProduits(),
            'cartTotal'  => number_format($panierFrais->getTotalMontant(), 2, '.', ' '),
        ]);
    }

    $this->addFlash('success', 'Produit ajouté au panier !');
    return $this->redirectToRoute('app_marketplace_catalogue');
}
    #[Route('/marketplace/panier/update/{id}', name: 'app_marketplace_panier_update', methods: ['POST'])]
    public function modifierQuantite(
        int $id,
        Request $request,
        PanierRepository $panierRepository,
        PanierProduitRepository $ppRepository,
        ProduitsRepository $produitsRepository
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);
        $panier = $panierRepository->getOrCreatePanier($user);
        $produit = $produitsRepository->find($id);

        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest' || $request->headers->get('Accept') === 'application/json';

        if (!$produit) {
            if ($isAjax) return $this->json(['success' => false, 'message' => 'Produit introuvable.'], 404);
            return $this->redirectToRoute('app_marketplace_panier');
        }

        $quantite = (int) $request->request->get('quantite');
        if ($quantite > $produit->getQuantiteStock()) {
            if ($isAjax) return $this->json(['success' => false, 'message' => 'Stock insuffisant pour ce produit.'], 400);
            $this->addFlash('warning', 'Stock insuffisant pour ce produit.');
            return $this->redirectToRoute('app_marketplace_panier');
        }

        $ppRepository->modifierQuantite($panier, $produit, $quantite);

        // Recharger pour recalculer les totaux
        $panierFrais = $panierRepository->findPanierWithProduits((int) $panier->getId()) ?? $panier;
        $ligne = $ppRepository->findOneBy(['panier' => $panierFrais, 'produit' => $produit]);

        if ($isAjax) {
            return $this->json([
                'success'       => true,
                'message'       => 'Quantité mise à jour.',
                'rowSubTotal'   => $ligne ? number_format($ligne->getSousTotal(), 2, '.', ' ') : '0.00',
                'cartCount'     => $panierFrais->getTotalProduits(),
                'cartTotal'     => number_format($panierFrais->getTotalMontant(), 2, '.', ' ')
            ]);
        }

        $this->addFlash('success', 'Panier mis à jour.');
        return $this->redirectToRoute('app_marketplace_panier');
    }

    #[Route('/marketplace/panier/delete/{id}', name: 'app_marketplace_panier_delete', methods: ['POST'])]
    public function supprimerLigne(
        int $id,
        Request $request,
        PanierRepository $panierRepository,
        PanierProduitRepository $ppRepository,
        ProduitsRepository $produitsRepository
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);
        $panier = $panierRepository->getOrCreatePanier($user);
        $produit = $produitsRepository->find($id);
        
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        if ($produit) {
            $ppRepository->supprimerLigne($panier, $produit);
            
            if ($isAjax) {
                // Recharger
                $panierFrais = $panierRepository->findPanierWithProduits((int) $panier->getId()) ?? $panier;
                return $this->json([
                    'success'   => true,
                    'message'   => 'Produit retiré.',
                    'cartCount' => $panierFrais->getTotalProduits(),
                    'cartTotal' => number_format($panierFrais->getTotalMontant(), 2, '.', ' ')
                ]);
            }
            
            $this->addFlash('success', 'Produit retiré du panier.');
        } else if ($isAjax) {
           return $this->json(['success' => false, 'message' => 'Produit introuvable.'], 404);
        }

        return $this->redirectToRoute('app_marketplace_panier');
    }

    #[Route('/marketplace/panier/clear', name: 'app_marketplace_panier_clear', methods: ['POST'])]
    public function viderPanier(Request $request, PanierRepository $panierRepository): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);
        $panierRepository->viderPanierUser($user);

        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';
        if ($isAjax) {
            return $this->json([
                'success'   => true,
                'message'   => 'Le panier a été vidé.',
                'cartCount' => 0,
                'cartTotal' => '0.00'
            ]);
        }

        $this->addFlash('success', 'Le panier a été vidé.');
        return $this->redirectToRoute('app_marketplace_panier');
    }
}
