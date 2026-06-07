<?php

namespace App\Service\UserAndDiag;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WhatsAppService
{
    private const TWILIO_API_URL = 'https://api.twilio.com/2010-04-01/Accounts/%s/Messages.json';

    public function __construct(
        private HttpClientInterface $client,
        private LoggerInterface $logger
    ) {}

    /**
     * Sends a WhatsApp message to the specified phone number.
     *
     * @param string $toPhoneNumber Full phone number with country code (e.g., "+21612345678")
     * @param string $body          Message body
     * @return bool                 True if successful
     */
    public function sendWhatsAppMessage(string $toPhoneNumber, string $body): bool
    {
        // Ensure the number has the whatsapp: prefix
        $to = str_starts_with($toPhoneNumber, 'whatsapp:') ? $toPhoneNumber : 'whatsapp:' . $toPhoneNumber;
        $accountSid = $_ENV['TWILIO_SID'] ?? '';
        $authToken = $_ENV['TWILIO_TOKEN'] ?? '';
        $fromWhatsapp = $_ENV['TWILIO_WHATSAPP_FROM'] ?? 'whatsapp:+14155238886';

        $url = sprintf(self::TWILIO_API_URL, $accountSid);

        try {
            $response = $this->client->request('POST', $url, [
                'auth_basic' => [$accountSid, $authToken],
                'body' => [
                    'To' => $to,
                    'From' => $fromWhatsapp,
                    'Body' => $body
                ]
            ]);

            $statusCode = $response->getStatusCode();

            if ($statusCode === 200 || $statusCode === 201) {
                $this->logger->info("WhatsApp message sent successfully to " . $toPhoneNumber);
                return true;
            }

            // Log detailed error from Twilio
            $errorData = $response->toArray(false);
            $this->logger->error("Twilio WhatsApp Error ($statusCode): " . ($errorData['message'] ?? 'Unknown error'));
            
            return false;

        } catch (\Exception $e) {
            $this->logger->error("Failed to send WhatsApp message: " . $e->getMessage());
            return false;
        }
    }
}