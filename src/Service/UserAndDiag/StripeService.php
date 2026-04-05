<?php

namespace App\Service\UserAndDiag;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Utils\UserAndDiag\LogUtils;

class StripeService
{
    private string $stripeSecretKey;
    private HttpClientInterface $httpClient;
    private RequestStack $requestStack;

    public function __construct(HttpClientInterface $httpClient, RequestStack $requestStack)
    {
        // Hardcoded key as per user's JavaFX code for testing.
        // In production this should be $_ENV['STRIPE_SECRET_KEY']
        $this->stripeSecretKey = "sk_test_51T5AXmGVNitoJdaIV0Z33VLG9vKRk0tUTsKbirlLbxwz7oceTClrPGPBMMU5Axw8f7QWaD59I7ZOgTn7pT5QZ5Z700z4GmwgVA";
        $this->httpClient = $httpClient;
        $this->requestStack = $requestStack;
    }

    public function createCheckoutSession(string $productName, int $amountCents, string $currency, array $metadata = []): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $baseUri = $request ? $request->getSchemeAndHttpHost() : 'https://ardhi.tn';

        // Using proper nested arrays which Symfony HttpClient will automatically 
        // format into line_items[0][price_data][currency]=... correctly
        $body = [
            'mode' => 'payment',
            'success_url' => $baseUri . '/user-and-diag/subscription/payment-success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUri . '/user-and-diag/subscription/payment-cancelled',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => $currency,
                        'product_data' => [
                            'name' => $productName,
                        ],
                        'unit_amount' => $amountCents,
                    ],
                    'quantity' => 1,
                ]
            ],
            'metadata' => $metadata
        ];

        try {
            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/checkout/sessions', [
                'auth_basic' => [$this->stripeSecretKey, ''],
                'body' => $body
            ]);

            $data = $response->toArray();
            return ['success' => true, 'sessionId' => $data['id'], 'checkoutUrl' => $data['url']];
        } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
            // Read Stripe's precise error response
            $errorContent = $e->getResponse()->getContent(false);
            $parsed = json_decode($errorContent, true);
            $stripeMessage = $parsed['error']['message'] ?? $e->getMessage();
            return ['success' => false, 'error' => $stripeMessage];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    public function checkSessionStatus(string $sessionId): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://api.stripe.com/v1/checkout/sessions/' . $sessionId, [
                'auth_basic' => [$this->stripeSecretKey, '']
            ]);

            return $response->toArray(); // Returns the full session object including metadata
        } catch (\Exception $e) {
            return null;
        }
    }
}
