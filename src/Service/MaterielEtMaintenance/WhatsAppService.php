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
        $numeroNormalise = $this->normalizePhoneNumber($numero);
        if ($numeroNormalise === null) {
            $this->logger->warning('Envoi WhatsApp ignoré: numéro invalide.', [
                'numero_brut' => $numero,
            ]);

            return;
        }

        $to = 'whatsapp:' . $numeroNormalise;
        $from = str_starts_with($this->from, 'whatsapp:') ? $this->from : 'whatsapp:' . $this->from;

        try {
            $client = new Client($this->sid, $this->token);
            
            $client->messages->create($to, [
                'from' => $from,
                'body' => $message
            ]);

            $this->logger->info('Message WhatsApp envoyé avec succès.', [
                'to' => $to,
                'from' => $from,
            ]);
        } catch (\Exception $e) {
            // Le message WhatsApp ne doit pas bloquer le reste du processus
            $this->logger->error('Erreur lors de l\'envoi du message WhatsApp Twilio.', [
                'to' => $to,
                'from' => $from,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function normalizePhoneNumber(string $numero): ?string
    {
        $numero = trim($numero);
        if ($numero === '') {
            return null;
        }

        // Garde uniquement les chiffres et le + éventuel.
        $numero = preg_replace('/[^\d+]/', '', $numero) ?? '';

        // Convertit un préfixe international 00 en +
        if (str_starts_with($numero, '00')) {
            $numero = '+' . substr($numero, 2);
        }

        // Si le numéro est local tunisien (8 chiffres), on ajoute +216
        if (preg_match('/^\d{8}$/', $numero)) {
            $numero = '+216' . $numero;
        }

        // N'autorise le + qu'en première position.
        if (str_contains(substr($numero, 1), '+')) {
            return null;
        }

        if (!preg_match('/^\+\d{8,15}$/', $numero)) {
            return null;
        }

        return $numero;
    }
}
