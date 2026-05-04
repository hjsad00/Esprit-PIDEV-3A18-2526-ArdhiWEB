<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Produits;
use App\Repository\Marketplace\WishlistRepository;
use App\Service\TwilioService;
use Psr\Log\LoggerInterface;

class WishlistNotificationService
{
    private WishlistRepository $wishlistRepository;
    private TwilioService $twilioService;
    private LoggerInterface $logger;

    public function __construct(
        WishlistRepository $wishlistRepository,
        TwilioService $twilioService,
        LoggerInterface $logger
    ) {
        $this->wishlistRepository = $wishlistRepository;
        $this->twilioService = $twilioService;
        $this->logger = $logger;
    }

    /**
     * Notifie les utilisateurs qui ont le produit en favoris d'une baisse de prix
     * ou de l'annulation d'une promotion.
     */
    public function notifyUpdate(Produits $produit, float $oldPrice): void
    {
        $newPrice = $produit->getPrixFinal();

        // Si le prix n'a pas changé, on ne fait rien
        if ($newPrice === $oldPrice) {
            return;
        }

        $wishlistEntries = $this->wishlistRepository->findBy(['produit' => $produit]);
        if (empty($wishlistEntries)) {
            return;
        }

        foreach ($wishlistEntries as $entry) {
            $user = $entry->getUser();
            if (!$user) {
                continue;
            }

            $phone = $user->getPhone();

            if (!$phone) {
                continue;
            }

            if ($newPrice < $oldPrice) {
                // CAS 1 : BAISSE DE PRIX / PROMOTION
                $remiseType = $produit->getTypeRemise();
                $remiseValue = $produit->getRemise();
                $remiseText = "";

                if ($remiseType === 'POURCENTAGE') {
                    $remiseText = sprintf("Soit -%d%% !", $remiseValue);
                } elseif ($remiseType === 'FIXE') {
                    $remiseText = sprintf("Soit -%.2f DT !", $remiseValue);
                } else {
                    $economie = $oldPrice - $newPrice;
                    $pourcentage = round(($economie / $oldPrice) * 100);
                    $remiseText = sprintf("Soit -%d%% !", $pourcentage);
                }

                $message = sprintf(
                    "🌟 *Ardhi Marketplace* 🌟\n\n" .
                    "Salut %s ! 👋\n\n" .
                    "Bonne nouvelle ! Le produit *%s* que vous avez ajouté à vos favoris est maintenant en promotion ! 🔥\n\n" .
                    "💰 Ancien prix : %.2f DT\n" .
                    "📉 Nouveau prix : *%.2f DT* (%s)\n\n" .
                    "Profitez-en vite avant qu'il n'y ait plus de stock ! 🚀",
                    $user->getPrenom(),
                    $produit->getNom(),
                    $oldPrice,
                    $newPrice,
                    $remiseText
                );
            } else {
                // CAS 2 : HAUSSE DE PRIX / ANNULATION PROMO
                $message = sprintf(
                    "📢 *Ardhi Marketplace* 🌟\n\n" .
                    "Salut %s ! 👋\n\n" .
                    "Information : Le prix du produit *%s* que vous avez en favoris a été mis à jour. La promotion est terminée ou le prix a été ajusté.\n\n" .
                    "💰 Ancien prix : %.2f DT\n" .
                    "📈 Nouveau prix : *%.2f DT*\n\n" .
                    "Merci de votre confiance ! 🌿",
                    $user->getPrenom(),
                    $produit->getNom(),
                    $oldPrice,
                    $newPrice
                );
            }

            $this->twilioService->sendWhatsAppMessage($phone, $message);
            $this->logger->info('wishlist.notification.sent', ['phone' => $phone, 'product' => $produit->getId()]);
        }
    }

    /**
     * Notifie les utilisateurs qui ont le produit en favoris quand le stock devient faible.
     */
    public function notifyLowStock(Produits $produit, int $oldStock): void
    {
        $newStock = $produit->getQuantiteStock();

        // On ne notifie que si on vient de passer sous le seuil de 10
        if ($oldStock > 10 && $newStock <= 10) {
            $wishlistEntries = $this->wishlistRepository->findBy(['produit' => $produit]);
            if (empty($wishlistEntries)) {
                return;
            }

            foreach ($wishlistEntries as $entry) {
                $user = $entry->getUser();
                if (!$user) {
                    continue;
                }

                $phone = $user->getPhone();

                if (!$phone) {
                    continue;
                }

                $message = sprintf(
                    "⚠️ *STOCK FAIBLE - Ardhi Marketplace* ⚠️\n\n" .
                    "Salut %s ! 👋\n\n" .
                    "Attention ! Le produit *%s* que vous avez ajouté à vos favoris n'a plus que *%d* unités en stock ! 📢\n\n" .
                    "Profitez-en vite avant la rupture de stock complète ! 🚀",
                    $user->getPrenom(),
                    $produit->getNom(),
                    $newStock
                );

                $this->twilioService->sendWhatsAppMessage($phone, $message);
            }
        }
    }

    /**
     * Notifie le vendeur quand le stock du produit atteint 0.
     */
    public function notifySellerOutOfStock(Produits $produit, int $oldStock): void
    {
        $newStock = $produit->getQuantiteStock();

        // Notification uniquement lors du passage vers 0.
        if (!($oldStock > 0 && $newStock === 0)) {
            return;
        }

        $seller = $produit->getUser();
        if (!$seller || !$seller->getPhone()) {
            return;
        }
// Message de notification pour le vendeur

        $message = sprintf(
            "⚠️ *Ardhi Marketplace* ⚠️\n\n" .
            "Salut %s ! 👋\n\n" .
            "Le produit *%s* est en rupture de stock (0).\n" .
            "Il est retire automatiquement du catalogue public.\n\n" .
            "La republication nécessite un stock disponible.",
            $seller->getPrenom() ?: 'Vendeur',
            $produit->getNom()
        );

        $this->twilioService->sendWhatsAppMessage($seller->getPhone(), $message);
    }
}
