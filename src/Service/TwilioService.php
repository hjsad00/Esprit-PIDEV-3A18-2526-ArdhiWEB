<?php

namespace App\Service;

use Twilio\Rest\Client;
use Psr\Log\LoggerInterface;

class TwilioService
{
    private string $sid;
    private string $token;
    private string $from;
    private LoggerInterface $logger;

    public function __construct(
        string $sid,
        string $token,
        string $from,
        LoggerInterface $logger
    ) {
        $this->sid = $sid;
        $this->token = $token;
        $this->from = $from;
        $this->logger = $logger;
    }

    /**
     * Envoie un message WhatsApp via Twilio.
     */
    public function sendWhatsAppMessage(string $to, string $message): bool
    {
        // Twilio require le préfixe "whatsapp:" pour les deux numéros
        if (strpos($this->from, 'whatsapp:') !== 0) {
            $from = 'whatsapp:' . $this->from;
        } else {
            $from = $this->from;
        }

        if (strpos($to, 'whatsapp:') !== 0) {
            $to = 'whatsapp:' . $to;
        }

        try {
            $client = new Client($this->sid, $this->token);
            $client->messages->create($to, [
                'from' => $from,
                'body' => $message
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Erreur Twilio WhatsApp : ' . $e->getMessage());
            return false;
        }
    }
}
