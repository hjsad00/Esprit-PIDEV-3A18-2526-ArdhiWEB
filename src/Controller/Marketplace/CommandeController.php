<?php

namespace App\Controller\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\DetailsCommande;
use App\Entity\UserAndDiag\User;
use App\Repository\Marketplace\CommandeRepository;
use App\Repository\Marketplace\NotifMarketRepository;
use App\Repository\Marketplace\PanierRepository;
use App\Repository\Marketplace\CouponRepository;
use App\Entity\Marketplace\CouponUtilisation;
use App\Service\Marketplace\CommandeInvoicePdfGenerator;
use App\Service\Marketplace\WishlistNotificationService;
use App\Service\Marketplace\OrderEmailService;
use App\Service\Marketplace\StripeCheckoutService;
use App\Service\Marketplace\MarketplaceQrService;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;
use Karser\Recaptcha3Bundle\Validator\Constraints\Recaptcha3;

/**
 * Contrôleur dédié à la gestion des commandes Marketplace.
 */
class CommandeController extends AbstractController
{
    /**
     * Page "Mes Commandes" — Vue acheteur.
     */
    #[Route('/marketplace/mes-commandes', name: 'app_marketplace_mes_commandes')]
    public function mesCommandes(
        Request $request,
        CommandeRepository $commandeRepo,
        PaginatorInterface $paginator
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);

        $commandesRaw = $commandeRepo->findByUser($user);
        $commandes = $paginator->paginate(
            $commandesRaw,
            max(1, $request->query->getInt('page', 1)),
            6
        );
        $stats = $commandeRepo->getStatsForBuyer($user);

        return $this->render('Marketplace/mes_commandes.html.twig', [
            'commandes' => $commandes,
            'stats' => $stats,
        ]);
    }

    /**
     * Page "Commandes Reçues" — Vue vendeur.
     */
    #[Route('/marketplace/commandes-recues', name: 'app_marketplace_commandes_recues')]
    public function commandesRecues(
        Request $request,
        CommandeRepository $commandeRepo,
        PaginatorInterface $paginator,
        MarketplaceQrService $marketplaceQrService,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);

        $commandesRaw = $commandeRepo->findOrdersBySeller($user);
        
        $commandes = $paginator->paginate(
            $commandesRaw,
            max(1, $request->query->getInt('page', 1)),
            6
        );
        $stats = $commandeRepo->getStatsForSeller($user);

        return $this->render('Marketplace/commandes_recues.html.twig', [
            'commandes' => $commandes,
            'stats' => $stats,
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
        $user = $this->getUser();
        assert($user instanceof User);
        $isAdmin = $this->isGranted('ROLE_ADMIN');
        $commandeUser = $commande->getUser();
        $isOwner = $commandeUser !== null && $commandeUser->getId() === $user->getId();
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
                'nom' => $produit ? $produit->getNom() : 'Produit supprimé',
                'image' => $produit ? $produit->getImage() : null,
                'quantite' => $detail->getQuantite(),
                'prixUnitaire' => number_format($detail->getPrixUnitaire(), 2, ',', ' '),
                'sousTotal' => number_format($detail->getSousTotal(), 2, ',', ' '),
                'vendeur' => $produit && $produit->getUser() ? $produit->getUser()->getNom() . ' ' . $produit->getUser()->getPrenom() : '—',
            ];
        }

        return $this->json([
            'success' => true,
            'commande' => [
                'id' => $commande->getId(),
                'date' => $commande->getDateCommande()->format('d/m/Y'),
                'etat' => $commande->getEtat(),
                'total' => number_format((float) $commande->getTotal(), 2, ',', ' '),
                'fraisLivraison' => number_format((float) $commande->getFraisLivraison(), 2, ',', ' '),
                'modeLivraison' => $commande->getModeLivraison() ?? 'RECUPERATION',
                'codeCoupon' => $commande->getCoupon() ? $commande->getCoupon()->getCode() : null,
                'montantRemise' => $commande->getMontantRemise() > 0 ? number_format($commande->getMontantRemise(), 2, ',', ' ') : null,
                'items' => $items,
            ],
        ]);
    }

    /**
     * Mettre à jour l'état d'une commande (vendeur uniquement).
     */
    #[Route('/marketplace/commande/{id}/status/{status}', name: 'app_marketplace_commande_update_status', methods: ['POST'])]
    public function updateStatus(
        int $id,
        string $status,
        CommandeRepository $commandeRepo,
        EntityManagerInterface $em,
        WorkflowInterface $materielLifecycleStateMachine,
        OrderEmailService $orderEmailService,
        NotifMarketRepository $notifMarketRepository
    ): JsonResponse {
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
        $user = $this->getUser();
        assert($user instanceof User);
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

        // Quand le vendeur accepte la commande, tout matériel en vente passe à vendu
        // et son produit lié est verrouillé à 0 stock.
        if ($status === 'en_cours' && $oldStatus !== 'en_cours') {
            foreach ($commande->getDetails() as $detail) {
                $produit = $detail->getProduit();
                if (!$produit || !$produit->getMateriel()) {
                    continue;
                }

                $materiel = $produit->getMateriel();
                $etatMateriel = mb_strtolower((string) $materiel->getEtat());
                $estEnVente = $materiel->getStatut() === 'en_vente' || $etatMateriel === 'en vente';

                if (!$estEnVente) {
                    continue;
                }

                if ($materielLifecycleStateMachine->can($materiel, 'vendre')) {
                    $materielLifecycleStateMachine->apply($materiel, 'vendre');
                } else {
                    $materiel->setStatut('vendu');
                }

                if ($produit->getQuantiteStock() !== 0) {
                    $produit->setQuantiteStock(0);
                }
            }
        }

        // Gérer le stock et les points en cas d'annulation
        if ($status === 'annulee' && $oldStatus !== 'annulee') {
            // Rendre le stock
            foreach ($commande->getDetails() as $detail) {
                $produit = $detail->getProduit();
                if ($produit) {
                    $produit->setQuantiteStock($produit->getQuantiteStock() + $detail->getQuantite());
                }
            }

            // Gestion de la Fidélité (Remboursement ou Retrait du bonus)
            $acheteur = $commande->getUser();
            if ($acheteur) {
                if ($commande->isPayeeParPoints()) {
                    // Si payée par points, on les rend
                    $acheteur->setPointsFidelite($acheteur->getPointsFidelite() + $commande->getTotal());
                } else {
                    // Si payée par carte, on retire le bonus de 10% qui avait été gagné
                    $acheteur->setPointsFidelite($acheteur->getPointsFidelite() - ($commande->getTotal() * 0.1));
                }
            }
        }

        $em->flush();

        // Notification de l'acheteur si le statut passe à "En cours" ou "Annulée"
        if ($status === 'en_cours' || $status === 'annulee') {
            $orderEmailService->sendOrderStatusUpdateBuyerNotification($commande);
        }

        // Notification in-app pour l'acheteur sur tout changement de statut.
        $commandeUser = $commande->getUser();
        if ($commandeUser) {
            $notifMarketRepository->notifierChangementStatutCommande(
                (int) $commandeUser->getId(),
                (int) $commande->getId(),
                $status,
                (float) $commande->getTotal()
            );
        }

        return $this->json([
            'success' => true,
            'message' => 'État mis à jour avec succès.',
            'etat' => $status,
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
    public function checkout(
        Request $request,
        PanierRepository $panierRepo,
        CouponRepository $couponRepo,
        EntityManagerInterface $em,
        WishlistNotificationService $notificationService,
        OrderEmailService $orderEmailService,
        NotifMarketRepository $notifMarketRepository,
        StripeCheckoutService $stripeCheckoutService,
        ValidatorInterface $validator
    ): JsonResponse {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);

        // 1. Récupérer le panier de l'utilisateur
        $panier = $panierRepo->findOneBy(['user' => $user]);
        if (!$panier || $panier->getPanierProduits()->isEmpty()) {
            return $this->json(['success' => false, 'message' => 'Votre panier est vide.'], 400);
        }

        // 2. Récupérer données requête
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return $this->json(['success' => false, 'message' => 'Payload checkout invalide.'], 400);
        }

        $captchaToken = $data['captcha_token'] ?? null;
        $violations = $validator->validate($captchaToken, new Recaptcha3());
        if (count($violations) > 0) {
            return $this->json([
                'success' => false,
                'message' => ($violations[0] ?? null)?->getMessage() ?: 'Captcha invalide, veuillez réessayer.',
            ], 400);
        }

        $modeLivraison = $data['mode_livraison'] ?? 'RECUPERATION';
        $paymentMethod = $data['payment_method'] ?? 'stripe';
        $couponCode = $data['coupon_code'] ?? null;

        $prepared = $this->prepareCheckoutData($user, $panier, $modeLivraison, $couponCode, $couponRepo, $em);
        if (!$prepared['success']) {
            return $this->json(['success' => false, 'message' => $prepared['message']], 400);
        }

        if ($paymentMethod === 'points') {
            if ($user->getPointsFidelite() < $prepared['totalFinalGlobal']) {
                return $this->json([
                    'success' => false,
                    'message' => 'Points de fidélité insuffisants (Solde : ' . $user->getPointsFidelite() . ' pts).',
                ], 400);
            }

            $user->setPointsFidelite($user->getPointsFidelite() - $prepared['totalFinalGlobal']);
            $result = $this->finalizeCheckout(
                $user,
                $panier,
                $prepared,
                'points',
                $em,
                $notificationService,
                $orderEmailService,
                $notifMarketRepository
            );

            return $this->json([
                'success' => true,
                'message' => $result['nbCommandes'] > 1
                    ? $result['nbCommandes'] . ' commandes créées avec succès !'
                    : 'Commande créée avec succès !',
                'nbCommandes' => $result['nbCommandes'],
            ]);
        }

        if ($paymentMethod === 'stripe') {
            if ($prepared['totalFinalGlobal'] < 3) {
                return $this->json([
                    'success' => false,
                    'message' => 'Le montant minimum de commande est de 3.00 DT.',
                ], 400);
            }

            $metadata = [
                'module' => 'marketplace',
                'user_id' => (string) $user->getId(),
                'mode_livraison' => $modeLivraison,
                'coupon_code' => (string) ($prepared['couponCode'] ?? ''),
            ];

            $checkout = $stripeCheckoutService->createCheckoutSession(
                $prepared['totalFinalGlobal'],
                'Commande Ardhi Marketplace',
                $metadata
            );

            if (!$checkout['success']) {
                $checkoutError = (string) ($checkout['error'] ?? 'Inconnue');
                if (str_contains($checkoutError, 'Montant trop faible pour Stripe')) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Le montant minimum de commande est de 3.00 DT.',
                    ], 400);
                }

                return $this->json([
                    'success' => false,
                    'message' => 'Erreur Stripe: ' . $checkoutError,
                ], 500);
            }

            return $this->json([
                'success' => true,
                'checkoutUrl' => (string) ($checkout['checkoutUrl'] ?? ''),
                'message' => 'Redirection vers Stripe en cours...',
            ]);
        }

        return $this->json(['success' => false, 'message' => 'Méthode de paiement invalide.'], 400);
    }

    #[Route('/marketplace/payment/success', name: 'app_marketplace_payment_success', methods: ['GET'])]
    public function paymentSuccess(
        Request $request,
        PanierRepository $panierRepo,
        CouponRepository $couponRepo,
        EntityManagerInterface $em,
        WishlistNotificationService $notificationService,
        OrderEmailService $orderEmailService,
        NotifMarketRepository $notifMarketRepository,
        StripeCheckoutService $stripeCheckoutService
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        assert($user instanceof User);
        $sessionId = (string) $request->query->get('session_id', '');

        if (!$sessionId) {
            $this->addFlash('danger', 'Session Stripe invalide.');
            return $this->redirectToRoute('app_marketplace_panier');
        }

        $session = $request->getSession();
        $processedSessions = $session->get('marketplace_processed_stripe_sessions', []);
        if (in_array($sessionId, $processedSessions, true)) {
            $this->addFlash('info', 'Cette session Stripe a déjà été traitée.');
            return $this->redirectToRoute('app_marketplace_mes_commandes');
        }

        $sessionData = $stripeCheckoutService->checkSessionStatus($sessionId);
        if (!$sessionData || ($sessionData['payment_status'] ?? null) !== 'paid') {
            $this->addFlash('danger', 'Le paiement n\'a pas été validé.');
            return $this->redirectToRoute('app_marketplace_panier');
        }

        $metadata = $sessionData['metadata'] ?? [];
        if ((string) ($metadata['module'] ?? '') !== 'marketplace') {
            $this->addFlash('danger', 'Session Stripe non autorisée.');
            return $this->redirectToRoute('app_marketplace_panier');
        }

        if ((string) ($metadata['user_id'] ?? '') !== (string) $user->getId()) {
            $this->addFlash('danger', 'Cette session Stripe ne vous appartient pas.');
            return $this->redirectToRoute('app_marketplace_panier');
        }

        $panier = $panierRepo->findOneBy(['user' => $user]);
        if (!$panier || $panier->getPanierProduits()->isEmpty()) {
            $this->addFlash('warning', 'Votre panier est vide, aucune commande n\'a été créée.');
            return $this->redirectToRoute('app_marketplace_catalogue');
        }

        $modeLivraison = ($metadata['mode_livraison'] ?? 'RECUPERATION') === 'LIVRAISON' ? 'LIVRAISON' : 'RECUPERATION';
        $couponCode = trim((string) ($metadata['coupon_code'] ?? ''));
        $couponCode = $couponCode !== '' ? $couponCode : null;

        $prepared = $this->prepareCheckoutData($user, $panier, $modeLivraison, $couponCode, $couponRepo, $em);
        if (!$prepared['success']) {
            $this->addFlash('danger', $prepared['message']);
            return $this->redirectToRoute('app_marketplace_panier');
        }

        $this->finalizeCheckout(
            $user,
            $panier,
            $prepared,
            'stripe',
            $em,
            $notificationService,
            $orderEmailService,
            $notifMarketRepository
        );

        $processedSessions[] = $sessionId;
        $session->set('marketplace_processed_stripe_sessions', array_values(array_unique($processedSessions)));

        $this->addFlash('success', 'Paiement validé, votre commande a été créée.');
        return $this->redirectToRoute('app_marketplace_mes_commandes');
    }

    #[Route('/marketplace/payment/cancelled', name: 'app_marketplace_payment_cancelled', methods: ['GET'])]
    public function paymentCancelled(): Response
    {
        $this->addFlash('warning', 'Le paiement a été annulé.');
        return $this->redirectToRoute('app_marketplace_panier');
    }

    /**
     * Prépare les données de checkout (coupon, stock, totaux, groupes vendeurs).
     *
     * @return array<string, mixed>
     */
    private function prepareCheckoutData(
        User $user,
        \App\Entity\Marketplace\Panier $panier,
        string $modeLivraison,
        ?string $couponCode,
        CouponRepository $couponRepo,
        EntityManagerInterface $em
    ): array {
        $fraisParVendeur = ($modeLivraison === 'LIVRAISON') ? 7.0 : 0.0;

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

        $groupesParVendeur = [];
        $totalBasket = 0.0;
        foreach ($panier->getPanierProduits() as $ligne) {
            $produit = $ligne->getProduit();
            if (!$produit) {
                continue;
            }

            $vendeur = $produit->getUser();
            if ($vendeur === null) {
                continue;
            }

            if ($produit->getQuantiteStock() < $ligne->getQuantite()) {
                return [
                    'success' => false,
                    'message' => 'Stock insuffisant pour le produit : ' . $produit->getNom() . '. Disponible : ' . $produit->getQuantiteStock(),
                ];
            }

            $vendeurId = $vendeur->getId();
            if (!isset($groupesParVendeur[$vendeurId])) {
                $groupesParVendeur[$vendeurId] = [];
            }
            $groupesParVendeur[$vendeurId][] = $ligne;
            $totalBasket += $produit->getPrixFinal() * $ligne->getQuantite();
        }

        if (empty($groupesParVendeur)) {
            return ['success' => false, 'message' => 'Votre panier ne contient aucun produit commandable.'];
        }

        if ($coupon && $totalBasket < $coupon->getMontantMin()) {
            $coupon = null;
        }

        $totalFinalGlobal = $totalBasket + (count($groupesParVendeur) * $fraisParVendeur);
        if ($coupon) {
            if ($coupon->getTypeReduction() === 'POURCENTAGE') {
                $totalFinalGlobal -= ($totalBasket * ($coupon->getValeur() / 100));
            } else {
                $totalFinalGlobal -= min($coupon->getValeur(), $totalBasket);
            }
        }
        $totalFinalGlobal = round($totalFinalGlobal, 2);

        return [
            'success' => true,
            'coupon' => $coupon,
            'couponCode' => $coupon ? $coupon->getCode() : null,
            'groupesParVendeur' => $groupesParVendeur,
            'totalBasket' => $totalBasket,
            'totalFinalGlobal' => $totalFinalGlobal,
            'fraisParVendeur' => $fraisParVendeur,
            'modeLivraison' => $modeLivraison,
        ];
    }

    /**
     * Finalise la création des commandes et notifications.
     *
     * @param array<string, mixed> $prepared
     * @return array{nbCommandes:int}
     */
    private function finalizeCheckout(
        User $user,
        \App\Entity\Marketplace\Panier $panier,
        array $prepared,
        string $paymentMethod,
        EntityManagerInterface $em,
        WishlistNotificationService $notificationService,
        OrderEmailService $orderEmailService,
        NotifMarketRepository $notifMarketRepository
    ): array {
        $coupon = $prepared['coupon'];
        $groupesParVendeur = $prepared['groupesParVendeur'];
        $totalBasket = $prepared['totalBasket'];
        $fraisParVendeur = $prepared['fraisParVendeur'];
        $modeLivraison = $prepared['modeLivraison'];
        $totalFinalGlobal = $prepared['totalFinalGlobal'];

        if ($paymentMethod !== 'points') {
            $pointsGagnes = $totalFinalGlobal * 0.1;
            $user->setPointsFidelite($user->getPointsFidelite() + $pointsGagnes);
        }

        $commandesCreees = [];
        $stockUpdates = [];
        $nbCommandes = 0;

        foreach ($groupesParVendeur as $lignes) {
            $commande = new Commande();
            $commande->setUser($user);
            $commande->setDateCommande(new \DateTime());
            $commande->setEtat('en_attente');
            $commande->setModeLivraison($modeLivraison);
            $commande->setFraisLivraison($fraisParVendeur);
            $commande->setPayeeParPoints($paymentMethod === 'points');

            $sousTotal = 0.0;
            foreach ($lignes as $ligne) {
                $detail = new DetailsCommande();
                $detail->setProduit($ligne->getProduit());
                $detail->setQuantite($ligne->getQuantite());
                $detail->setPrixUnitaire($ligne->getProduit()->getPrixFinal());
                $commande->addDetail($detail);

                $produit = $ligne->getProduit();
                if ($produit) {
                    $oldStock = $produit->getQuantiteStock();
                    $produit->setQuantiteStock($oldStock - $ligne->getQuantite());
                    $stockUpdates[] = ['produit' => $produit, 'oldStock' => $oldStock];
                }

                $sousTotal += $ligne->getProduit()->getPrixFinal() * $ligne->getQuantite();
            }

            $remiseSurCetteCommande = 0.0;
            if ($coupon) {
                if ($coupon->getTypeReduction() === 'POURCENTAGE') {
                    $remiseSurCetteCommande = $sousTotal * ($coupon->getValeur() / 100);
                } else {
                    $poids = ($totalBasket > 0) ? ($sousTotal / $totalBasket) : 0;
                    $remiseSurCetteCommande = $coupon->getValeur() * $poids;
                }

                $remiseSurCetteCommande = min($remiseSurCetteCommande, $sousTotal);
                $commande->setCoupon($coupon);
                $commande->setMontantRemise(round($remiseSurCetteCommande, 3));
            }

            $commande->setTotal(round(($sousTotal - $remiseSurCetteCommande) + $fraisParVendeur, 2));
            $em->persist($commande);
            $commandesCreees[] = $commande;
            $nbCommandes++;
        }

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

        foreach ($panier->getPanierProduits()->toArray() as $ligne) {
            $em->remove($ligne);
        }
        $panier->setTotalMontant(0);
        $panier->setTotalProduits(0);

        $em->flush();

        foreach ($commandesCreees as $cmd) {
            $firstDetail = $cmd->getDetails()->first();
            if ($firstDetail && $firstDetail->getProduit() && $firstDetail->getProduit()->getUser()) {
                $seller = $firstDetail->getProduit()->getUser();
                $buyerName = trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''));

                $notifMarketRepository->notifierNouvelleCommande(
                    (int) $seller->getId(),
                    (int) $cmd->getId(),
                    (int) $firstDetail->getProduit()->getId(),
                    $buyerName !== '' ? $buyerName : 'Acheteur',
                    $cmd->getTotal()
                );
            }

            $orderEmailService->sendNewOrderSellerNotification($cmd);
        }

        foreach ($stockUpdates as $update) {
            $notificationService->notifyLowStock($update['produit'], $update['oldStock']);
            $notificationService->notifySellerOutOfStock($update['produit'], $update['oldStock']);
        }

        return ['nbCommandes' => $nbCommandes];
    }

    /**
     * Génère et télécharge la facture PDF d'une commande
     */
    #[Route('/marketplace/commande/{id}/facture-pdf', name: 'app_marketplace_commande_pdf')]
    public function facturePdf(
        Commande $commande,
        MarketplaceQrService $marketplaceQrService,
        CommandeInvoicePdfGenerator $commandeInvoicePdfGenerator
    ): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }
        $isAdmin = $this->isGranted('ROLE_ADMIN');

        // Sécurité : seul le propriétaire, le vendeur ou l'admin peut accéder
        $commandeUser = $commande->getUser();
        if (!$commandeUser) {
            throw $this->createAccessDeniedException('Commande sans utilisateur.');
        }

        $isOwner = $commandeUser->getId() === $user->getId();
        $isSeller = false;
        foreach ($commande->getDetails() as $detail) {
            if ($detail->getProduit() && $detail->getProduit()->getUser() && $detail->getProduit()->getUser()->getId() === $user->getId()) {
                $isSeller = true;
                break;
            }
        }

        if (!$isOwner && !$isSeller && !$isAdmin) {
            throw $this->createAccessDeniedException("Accès refusé. Vous ne pouvez pas télécharger cette facture.");
        }

        // --- GÉNÉRATION QR CODES ---
        // 1. QR par Produit
        $productQrCodes = [];
        foreach ($commande->getDetails() as $detail) {
            if ($detail->getProduit()) {
                // On génère le QR pour chaque produit (écrasera si le fichier existe déjà, 
                // ce qui permet de mettre à jour si l'URL Ngrok change)
                $productQrCodes[] = $marketplaceQrService->generateForProduct($detail->getProduit());
            }
        }

        $pdfContent = $commandeInvoicePdfGenerator->generate($commande, $productQrCodes);

        // Renvoyer le PDF pour le saut vers le téléchargement
        return new Response(
            $pdfContent,
            200,
            array(
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => sprintf('attachment; filename="facture_commande_%s.pdf"', $commande->getId())
            )
        );
    }
}
