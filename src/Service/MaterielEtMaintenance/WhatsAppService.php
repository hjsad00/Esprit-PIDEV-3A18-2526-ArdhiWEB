<?php

namespace App\Service\MaterielEtMaintenance;

use Twilio\Rest\Client;
use Psr\Log\LoggerInterface;

/**
 * Service pour l'envoi de messages WhatsApp via Twilio.
 */
class WhatsAppService
{
    private string $sid;
    private string $token;
    private string $from;
    private LoggerInterface $logger;

    public function __construct(string $twilioSid, string $twilioToken, string $twilioFrom, LoggerInterface $logger)
    {
        $this->sid = $twilioSid;
        $this->token = $twilioToken;
        $this->from = $twilioFrom;
        $this->logger = $logger;
    }

    /**
     * Envoie un message WhatsApp à un numéro donné.
     * 
     * @param string $numero Le numéro de destination (ex: +216XXXXXXXX)
     * @param string $message Le texte du message à envoyer
     */
    public function envoyer(string $numero, string $message): void
    {
        // On s'assure que le numéro ne contient pas déjà le préfixe whatsapp:
        $to = str_starts_with($numero, 'whatsapp:') ? $numero : 'whatsapp:' . $numero;

        try {
            $client = new Client($this->sid, $this->token);
            
            $client->messages->create($to, [
                'from' => $this->from,
                'body' => $message
            ]);

            $this->logger->info("Message WhatsApp envoyé avec succès à : " . $numero);
        } catch (\Exception $e) {
            // Le message WhatsApp ne doit pas bloquer le reste du processus
            $this->logger->error("Erreur lors de l'envoi du message WhatsApp Twilio : " . $e->getMessage());
        }
    }
}
