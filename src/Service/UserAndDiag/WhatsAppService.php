<?php

namespace App\Service\UserAndDiag;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WhatsAppService
{
    private const ACCOUNT_SID = 'AC9c3432ef37f1587d3c5aa66874381487';
    private const AUTH_TOKEN = '20ad44cd17c2b3de97087777dc451f58';
    private const FROM_WHATSAPP = 'whatsapp:+14155238886';
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
        $url = sprintf(self::TWILIO_API_URL, self::ACCOUNT_SID);

        try {
            $response = $this->client->request('POST', $url, [
                'auth_basic' => [self::ACCOUNT_SID, self::AUTH_TOKEN],
                'body' => [
                    'To' => $to,
                    'From' => self::FROM_WHATSAPP,
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