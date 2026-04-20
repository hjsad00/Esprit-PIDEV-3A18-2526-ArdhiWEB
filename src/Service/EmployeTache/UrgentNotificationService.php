<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Employe;
use Psr\Log\LoggerInterface;
use Twilio\Rest\Client;

/**
 * Service dédié aux envois de SMS et WhatsApp urgents via Twilio.
 */
class UrgentNotificationService
{
    private ?Client $twilioClient = null;
    private ?string $fromSms;
    private ?string $fromWhatsApp;

    public function __construct(
        private LoggerInterface $logger,
        ?string $twilioSid = null,
        ?string $twilioToken = null,
        ?string $twilioFrom = null,
        ?string $twilioWhatsAppFrom = null
    ) {
        $this->fromSms = $twilioFrom;
        $this->fromWhatsApp = $twilioWhatsAppFrom;

        if ($twilioSid && $twilioToken) {
            try {
                $this->twilioClient = new Client($twilioSid, $twilioToken);
            } catch (\Exception $e) {
                $this->logger->error('Erreur initialisation Twilio : ' . $e->getMessage());
            }
        }
    }

    /**
     * Envoie une notification urgente à un employé (SMS et/ou WhatsApp).
     * @param string $canal 'sms', 'whatsapp' ou 'both'
     */
    public function sendUrgentNotification(Employe $employe, string $message, string $canal = 'both'): void
    {
        if (!$this->twilioClient) {
            $this->logger->warning("Twilio n'est pas configuré. Impossible d'envoyer la notification à " . $employe->getNomComplet());
            return;
        }

        $telephone = $employe->getTelephone();
        if (!$telephone || strlen($telephone) !== 8) {
            $this->logger->warning("Numéro de téléphone invalide pour l'employé " . $employe->getNomComplet());
            return;
        }

        // Ajout de l'indicatif "+216" (Tunisie) par défaut pour les numéros à 8 chiffres
        // À adapter dynamiquement si l'application supporte plusieurs pays
        $telephoneInternational = '+216' . $telephone;

        if (in_array($canal, ['sms', 'both'])) {
            $this->sendSms($telephoneInternational, $message, $employe);
        }

        if (in_array($canal, ['whatsapp', 'both'])) {
            $this->sendWhatsApp($telephoneInternational, $message, $employe);
        }
    }

    private function sendSms(string $to, string $message, Employe $employe): void
    {
        if (!$this->fromSms) return;

        try {
            $this->twilioClient->messages->create(
                $to,
                [
                    'from' => $this->fromSms,
                    'body' => $message
                ]
            );
            $this->logger->info("SMS envoyé avec succès à " . $employe->getNomComplet() . " ($to).");
        } catch (\Exception $e) {
            $this->logger->error("Échec de l'envoi SMS à " . $employe->getNomComplet() . " : " . $e->getMessage());
        }
    }

    private function sendWhatsApp(string $to, string $message, Employe $employe): void
    {
        if (!$this->fromWhatsApp) return;

        try {
            $this->twilioClient->messages->create(
                "whatsapp:" . $to,
                [
                    'from' => "whatsapp:" . $this->fromWhatsApp,
                    'body' => $message
                ]
            );
            $this->logger->info("WhatsApp envoyé avec succès à " . $employe->getNomComplet() . " ($to).");
        } catch (\Exception $e) {
            $this->logger->error("Échec de l'envoi WhatsApp à " . $employe->getNomComplet() . " : " . $e->getMessage());
        }
    }
}
