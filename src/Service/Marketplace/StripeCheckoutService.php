<?php

namespace App\Service\Marketplace;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class StripeCheckoutService
{
    private const CURRENCY_API_URL = 'https://api.exchangerate-api.com/v4/latest/TND';
    private const EUR_FALLBACK_RATE = 0.30;
    private const MIN_AMOUNT_EUR_CENTS = 50;

    private string $stripeSecretKey;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly RequestStack $requestStack
    ) {
        $this->stripeSecretKey = trim((string) ($_ENV['MARKETPLACE_STRIPE_SECRET_KEY'] ?? ''));
    }

    /**
     * Cree une Stripe Checkout Session test et retourne son URL.
     *
     * @param array<string, string> $metadata
     * @return array{success:bool,checkoutUrl?:string,sessionId?:string,error?:string}
     */
    public function createCheckoutSession(float $totalTnd, string $label, array $metadata = []): array
    {
        if ($this->stripeSecretKey === '' || str_starts_with($this->stripeSecretKey, 'sk_test_VOTRE')) {
            return ['success' => false, 'error' => 'Cle Stripe non configuree (MARKETPLACE_STRIPE_SECRET_KEY).'];
        }

        if ($totalTnd <= 0) {
            return ['success' => false, 'error' => 'Montant invalide pour le paiement Stripe.'];
        }

        $eurRate = $this->getTndToEurRate();
        $totalEur = round($totalTnd * $eurRate, 2);

        // Stripe attend le plus petit sous-unite de devise: EUR => cents.
        $amountEurCents = (int) round($totalEur * 100);
        if ($amountEurCents < self::MIN_AMOUNT_EUR_CENTS) {
            return [
                'success' => false,
                'error' => sprintf(
                    'Montant trop faible pour Stripe : %.2f TND = %.2f EUR (%d cents, minimum %d cents).',
                    $totalTnd,
                    $totalEur,
                    $amountEurCents,
                    self::MIN_AMOUNT_EUR_CENTS
                ),
            ];
        }

        $request = $this->requestStack->getCurrentRequest();
        $baseUri = $request ? $request->getSchemeAndHttpHost() : 'http://localhost:8000';

        $body = [
            'mode' => 'payment',
            'success_url' => $baseUri . '/marketplace/payment/success?session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $baseUri . '/marketplace/payment/cancelled',
            'line_items' => [
                [
                    'price_data' => [
                        'currency' => 'eur',
                        'product_data' => ['name' => $label],
                        'unit_amount' => $amountEurCents,
                    ],
                    'quantity' => 1,
                ],
            ],
            'metadata' => array_merge($metadata, [
                'currency' => 'EUR',
                'amount_tnd' => number_format($totalTnd, 2, '.', ''),
                'amount_eur' => number_format($totalEur, 2, '.', ''),
                'tnd_to_eur_rate' => number_format($eurRate, 6, '.', ''),
            ]),
        ];

        try {
            $response = $this->httpClient->request('POST', 'https://api.stripe.com/v1/checkout/sessions', [
                'auth_basic' => [$this->stripeSecretKey, ''],
                'body' => $body,
            ]);

            $data = $response->toArray();
            return [
                'success' => true,
                'sessionId' => $data['id'] ?? null,
                'checkoutUrl' => $data['url'] ?? null,
            ];
        } catch (\Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface $e) {
            $errorContent = $e->getResponse()->getContent(false);
            $parsed = json_decode($errorContent, true);
            $message = $parsed['error']['message'] ?? $e->getMessage();

            return ['success' => false, 'error' => $message];
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function getTndToEurRate(): float
    {
        try {
            $response = $this->httpClient->request('GET', self::CURRENCY_API_URL);
            $data = $response->toArray();
            $rate = (float) ($data['rates']['EUR'] ?? 0);

            return $rate > 0 ? $rate : self::EUR_FALLBACK_RATE;
        } catch (\Throwable) {
            return self::EUR_FALLBACK_RATE;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function checkSessionStatus(string $sessionId): ?array
    {
        if ($this->stripeSecretKey === '') {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.stripe.com/v1/checkout/sessions/' . $sessionId, [
                'auth_basic' => [$this->stripeSecretKey, ''],
            ]);

            return $response->toArray();
        } catch (\Throwable) {
            return null;
        }
    }
}
