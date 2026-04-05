<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\DetailsCommande;
use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\CouponRepository;
use App\Entity\Marketplace\CouponUtilisation;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Contrôleur dédié à la gestion des commandes Marketplace.
 */
class CommandeController extends AbstractController
{
    /**
     * Page "Mes Commandes" — Vue acheteur.
     */
    #[Route('/marketplace/mes-commandes', name: 'app_marketplace_mes_commandes')]
    public function mesCommandes(CommandeRepository $commandeRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        $commandes = $commandeRepo->findByUser($user);
        $stats     = $commandeRepo->getStatsForBuyer($user);

        return $this->render('Marketplace/mes_commandes.html.twig', [
            'commandes' => $commandes,
            'stats'     => $stats,
        ]);
    }

    /**
     * Page "Commandes Reçues" — Vue vendeur.
     */
    #[Route('/marketplace/commandes-recues', name: 'app_marketplace_commandes_recues')]
    public function commandesRecues(CommandeRepository $commandeRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        $commandes = $commandeRepo->findOrdersBySeller($user);
        $stats     = $commandeRepo->getStatsForSeller($user);

        return $this->render('Marketplace/commandes_recues.html.twig', [
            'commandes' => $commandes,
            'stats'     => $stats,
        ]);
    }

    /**
     * Détails d'une commande (AJAX) — retourne du JSON.
     */
    #[Route('/marketplace/commande/{id}/details', name: 'app_marketplace_commande_details', methods: ['GET'])]
    public function detailsCommande(int $id, CommandeRepository $commandeRepo): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $commande = $commandeRepo->findCommandeWithDetails($id);
        if (!$commande) {
            return $this->json(['success' => false, 'message' => 'Commande introuvable.'], 404);
        }

        // Vérifier que l'utilisateur est bien l'acheteur, un vendeur concerné, ou un ADMIN
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $isOwner = $commande->getUser()->getId() === $user->getId();
        $isSeller = false;
        foreach ($commande->getDetails() as $detail) {
            if ($detail->getProduit() && $detail->getProduit()->getUser() && $detail->getProduit()->getUser()->getId() === $user->getId()) {
                $isSeller = true;
                break;
            }
        }
 
        if (!$isOwner && !$isSeller && !$isAdmin) {
            return $this->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $items = [];
        foreach ($commande->getDetails() as $detail) {
            $produit = $detail->getProduit();
            $items[] = [
                'nom'          => $produit ? $produit->getNom() : 'Produit supprimé',
                'image'        => $produit ? $produit->getImage() : null,
                'quantite'     => $detail->getQuantite(),
                'prixUnitaire' => number_format($detail->getPrixUnitaire(), 2, ',', ' '),
                'sousTotal'    => number_format($detail->getSousTotal(), 2, ',', ' '),
                'vendeur'      => $produit && $produit->getUser() ? $produit->getUser()->getNom() . ' ' . $produit->getUser()->getPrenom() : '—',
            ];
        }

        return $this->json([
            'success' => true,
            'commande' => [
                'id'    => $commande->getId(),
                'date'  => $commande->getDateCommande()->format('d/m/Y'),
                'etat'  => $commande->getEtat(),
                'total' => number_format($commande->getTotal(), 2, ',', ' '),
                'fraisLivraison' => number_format($commande->getFraisLivraison(), 2, ',', ' '),
                'modeLivraison'  => $commande->getModeLivraison() ?? 'RECUPERATION',
                'codeCoupon'     => $commande->getCoupon() ? $commande->getCoupon()->getCode() : null,
                'montantRemise'  => $commande->getMontantRemise() > 0 ? number_format($commande->getMontantRemise(), 2, ',', ' ') : null,
                'items' => $items,
            ],
        ]);
    }

    /**
     * Mettre à jour l'état d'une commande (vendeur uniquement).
     */
    #[Route('/marketplace/commande/{id}/status/{status}', name: 'app_marketplace_commande_update_status', methods: ['POST'])]
    public function updateStatus(int $id, string $status, CommandeRepository $commandeRepo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $validStatuses = ['en_attente', 'en_cours', 'livree', 'annulee'];
        if (!in_array($status, $validStatuses)) {
            return $this->json(['success' => false, 'message' => 'État invalide.'], 400);
        }

        $commande = $commandeRepo->find($id);
        if (!$commande) {
            return $this->json(['success' => false, 'message' => 'Commande introuvable.'], 404);
        }

        // Vérifier que l'utilisateur est vendeur de cette commande
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $isSeller = false;
        foreach ($commande->getDetails() as $detail) {
            if ($detail->getProduit() && $detail->getProduit()->getUser() && $detail->getProduit()->getUser()->getId() === $user->getId()) {
                $isSeller = true;
                break;
            }
        }

        if (!$isSeller) {
            return $this->json(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $oldStatus = $commande->getEtat();
        $commande->setEtat($status);

        // Gérer le stock en cas d'annulation
        if ($status === 'annulee' && $oldStatus !== 'annulee') {
            foreach ($commande->getDetails() as $detail) {
                $produit = $detail->getProduit();
                if ($produit) {
                    $produit->setQuantiteStock($produit->getQuantiteStock() + $detail->getQuantite());
                }
            }
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => 'État mis à jour avec succès.',
            'etat'    => $status,
        ]);
    }

    /**
     * Vérification asynchrone du coupon depuis le panier.
     */
    #[Route('/marketplace/panier/coupon/verify', name: 'app_marketplace_coupon_verify', methods: ['POST'])]
    public function verifyCoupon(Request $request, CouponRepository $couponRepo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        
        $data = json_decode($request->getContent(), true);
        $code = $data['code'] ?? '';
        $panierTotal = (float) ($data['panier_total'] ?? 0);

        $coupon = $couponRepo->findOneBy(['code' => strtoupper($code), 'actif' => true]);

        if (!$coupon) {
            return $this->json(['success' => false, 'message' => 'Code promo invalide ou inactif.']);
        }

        $now = new \DateTime();
        if ($now < $coupon->getDateDebut() || $now > $coupon->getDateFin()) {
            return $this->json(['success' => false, 'message' => 'Ce code promo a expiré ou n\'est pas encore valide.']);
        }

        if ($coupon->getMontantMin() > 0 && $panierTotal < $coupon->getMontantMin()) {
            return $this->json(['success' => false, 'message' => sprintf('Ce code nécessite un panier minimum de %.2f DT.', $coupon->getMontantMin())]);
        }

        if ($coupon->getUtilisationMax() > 0 && $coupon->getUtilisationActuelle() >= $coupon->getUtilisationMax()) {
            return $this->json(['success' => false, 'message' => 'Le quota d\'utilisation globale de ce code a été atteint.']);
        }

        $utilisation = $em->getRepository(CouponUtilisation::class)->findOneBy(['coupon' => $coupon, 'user' => $user]);
        if ($utilisation && $utilisation->getNombreUtilisation() >= $coupon->getLimiteParUser()) {
            return $this->json(['success' => false, 'message' => 'Vous avez déjà atteint votre limite personnelle pour ce code promo.']);
        }

        $remiseCalculee = 0;
        if ($coupon->getTypeReduction() === 'POURCENTAGE') {
            $remiseCalculee = $panierTotal * ($coupon->getValeur() / 100);
        } else {
            $remiseCalculee = $coupon->getValeur();
        }

        $remiseCalculee = min($remiseCalculee, $panierTotal);
        $remiseFormatee = $coupon->getTypeReduction() === 'POURCENTAGE' ? $coupon->getValeur() . '%' : number_format($coupon->getValeur(), 2, '.', ' ') . ' DT';

        return $this->json([
            'success' => true,
            'code' => $coupon->getCode(),
            'remise_calculee' => $remiseCalculee,
            'remise_formatee' => $remiseFormatee,
            'type' => $coupon->getTypeReduction(),
            'valeur' => $coupon->getValeur()
        ]);
    }

    /**
     * Passage de commande : transforme le panier en commande(s).
     * Crée une commande par vendeur et applique les remises proportionnelles.
     */
    #[Route('/marketplace/panier/checkout', name: 'app_marketplace_checkout', methods: ['POST'])]
    public function checkout(Request $request, PanierRepository $panierRepo, CouponRepository $couponRepo, EntityManagerInterface $em): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        // 1. Récupérer le panier de l'utilisateur
        $panier = $panierRepo->findOneBy(['user' => $user]);
        if (!$panier || $panier->getPanierProduits()->isEmpty()) {
            return $this->json(['success' => false, 'message' => 'Votre panier est vide.'], 400);
        }

        // 2. Récupérer données requête
        $data = json_decode($request->getContent(), true);
        $modeLivraison = $data['mode_livraison'] ?? 'RECUPERATION';
        $fraisParVendeur = ($modeLivraison === 'LIVRAISON') ? 7.0 : 0.0;
        $couponCode = $data['coupon_code'] ?? null;

        // 2.5 Validation Sécurisée du Coupon à l'instant T
        $coupon = null;
        if ($couponCode) {
            $coupon = $couponRepo->findOneBy(['code' => strtoupper($couponCode), 'actif' => true]);
            if ($coupon) {
                $now = new \DateTime();
                if ($now < $coupon->getDateDebut() || $now > $coupon->getDateFin() || ($coupon->getUtilisationMax() > 0 && $coupon->getUtilisationActuelle() >= $coupon->getUtilisationMax())) {
                    $coupon = null;
                } else {
                    $utilisation = $em->getRepository(CouponUtilisation::class)->findOneBy(['coupon' => $coupon, 'user' => $user]);
                    if ($utilisation && $utilisation->getNombreUtilisation() >= $coupon->getLimiteParUser()) {
                        $coupon = null;
                    }
                }
            }
        }

        // 3. Grouper les articles par vendeur et calculer Total Global pour algorithme proportionnel
        $groupesParVendeur = [];
        $totalBasket = 0.0;
        foreach ($panier->getPanierProduits() as $ligne) {
            $produit = $ligne->getProduit();
            $vendeur = $produit->getUser();
            if ($vendeur === null) {
                continue;
            }
            $vendeurId = $vendeur->getId();
            if (!isset($groupesParVendeur[$vendeurId])) {
                $groupesParVendeur[$vendeurId] = [];
            }
            $groupesParVendeur[$vendeurId][] = $ligne;
            
            // Total réel des items
            $totalBasket += $produit->getPrixFinal() * $ligne->getQuantite();
        }

        // Si total minimum non atteint pour ce coupon, ignorer coupon
        if ($coupon && $totalBasket < $coupon->getMontantMin()) {
            $coupon = null;
        }

        // 3.5. Vérifier les stocks avant création
        foreach ($panier->getPanierProduits() as $ligne) {
            $produit = $ligne->getProduit();
            if ($produit && $produit->getQuantiteStock() < $ligne->getQuantite()) {
                return $this->json([
                    'success' => false, 
                    'message' => 'Stock insuffisant pour le produit : ' . $produit->getNom() . '. Disponible : ' . $produit->getQuantiteStock()
                ], 400);
            }
        }

        // 4. Créer une commande par vendeur
        $nbCommandes = 0;
        foreach ($groupesParVendeur as $vendeurId => $lignes) {
            $commande = new Commande();
            $commande->setUser($user);
            $commande->setDateCommande(new \DateTime());
            $commande->setEtat('en_attente');
            $commande->setModeLivraison($modeLivraison);
            $commande->setFraisLivraison($fraisParVendeur);

            $sousTotal = 0.0;
            foreach ($lignes as $ligne) {
                $detail = new DetailsCommande();
                $detail->setProduit($ligne->getProduit());
                $detail->setQuantite($ligne->getQuantite());
                $detail->setPrixUnitaire($ligne->getProduit()->getPrixFinal());
                $commande->addDetail($detail);

                $produit = $ligne->getProduit();
                if ($produit) {
                    $produit->setQuantiteStock($produit->getQuantiteStock() - $ligne->getQuantite());
                }

                $sousTotal += $ligne->getProduit()->getPrixFinal() * $ligne->getQuantite();
            }

            // APPLIQUER LA RÉPARTITION DE LA REMISE
            $remiseSurCetteCommande = 0.0;
            if ($coupon) {
                if ($coupon->getTypeReduction() === 'POURCENTAGE') {
                    $remiseSurCetteCommande = $sousTotal * ($coupon->getValeur() / 100);
                } else {
                    // Montant Fixe : Pondération Proportionnelle
                    $poids = ($totalBasket > 0) ? ($sousTotal / $totalBasket) : 0;
                    $remiseSurCetteCommande = $coupon->getValeur() * $poids;
                }
                
                $remiseSurCetteCommande = min($remiseSurCetteCommande, $sousTotal);
                
                $commande->setCoupon($coupon);
                $commande->setMontantRemise(round($remiseSurCetteCommande, 3));
            }

            $commande->setTotal(round(($sousTotal - $remiseSurCetteCommande) + $fraisParVendeur, 2));
            $em->persist($commande);
            $nbCommandes++;
        }

        // 4.5. Enregistrement Utilisations Coupon
        if ($coupon) {
            $coupon->setUtilisationActuelle($coupon->getUtilisationActuelle() + 1);
            $utilisation = $em->getRepository(CouponUtilisation::class)->findOneBy(['coupon' => $coupon, 'user' => $user]);
            if (!$utilisation) {
                $utilisation = new CouponUtilisation();
                $utilisation->setCoupon($coupon);
                $utilisation->setUser($user);
                $em->persist($utilisation);
            }
            $utilisation->setNombreUtilisation($utilisation->getNombreUtilisation() + 1);
        }

        // 5. Vider le panier
        foreach ($panier->getPanierProduits()->toArray() as $ligne) {
            $em->remove($ligne);
        }
        $panier->setTotalMontant(0);
        $panier->setTotalProduits(0);

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => $nbCommandes > 1
                ? "$nbCommandes commandes créées avec succès !"
                : 'Commande créée avec succès !',
            'nbCommandes' => $nbCommandes,
        ]);
    }
}
