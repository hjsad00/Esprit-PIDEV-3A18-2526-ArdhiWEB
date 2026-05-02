<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Commande;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class OrderEmailService
{
    private MailerInterface $mailer;
    private string $mailFrom;

    public function __construct(MailerInterface $mailer, string $mailFrom)
    {
        $this->mailer = $mailer;
        $this->mailFrom = $mailFrom;
    }

    /**
     * Envoie une notification par email au vendeur pour une nouvelle commande.
     */
    public function sendNewOrderSellerNotification(Commande $commande): void
    {
        // On récupère le vendeur depuis le premier article de la commande
        // (Dans ce système, une commande est créée par vendeur)
        $details = $commande->getDetails();
        if ($details->isEmpty()) {
            return;
        }

        $firstDetail = $details->first();
        if (!$firstDetail) {
            return;
        }

        $produit = $firstDetail->getProduit();
        if (!$produit) {
            return;
        }

        $vendeur = $produit->getUser();
        if (!$vendeur || !$vendeur->getEmail()) {
            return;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Ardhi Marketplace'))
            ->to($vendeur->getEmail())
            ->subject('🌿 Nouvelle commande reçue sur Ardhi Marketplace !')
            ->htmlTemplate('Marketplace/Emails/seller_new_order.html.twig')
            ->context([
                'vendeur' => $vendeur,
                'commande' => $commande,
                'items' => $details,
            ]);

        $this->mailer->send($email);
    }

    /**
     * Envoie une notification par email à l'acheteur pour un changement de statut (En cours / Annulée).
     */
    public function sendOrderStatusUpdateBuyerNotification(Commande $commande): void
    {
        $acheteur = $commande->getUser();
        if (!$acheteur || !$acheteur->getEmail()) {
            return;
        }

        $sujet = ($commande->getEtat() === 'en_cours') 
            ? '🚚 Votre commande Ardhi est en cours de préparation !'
            : '❌ Notification concernant votre commande Ardhi';

        $firstDetail = $commande->getDetails()->first();
        if (!$firstDetail) {
            $vendeur = null;
        } else {
            $produit = $firstDetail->getProduit();
            $vendeur = $produit ? $produit->getUser() : null;
        }

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Ardhi Marketplace'))
            ->to($acheteur->getEmail())
            ->subject($sujet)
            ->htmlTemplate('Marketplace/Emails/acheteur_commande_statut.html.twig')
            ->context([
                'commande' => $commande,
                'vendeur' => $vendeur,
            ]);

        $this->mailer->send($email);
    }
}
