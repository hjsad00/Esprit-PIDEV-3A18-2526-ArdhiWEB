<?php

namespace App\Controller\Marketplace;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class CurrencyController extends AbstractController
{
    private const API_URL = "https://api.exchangerate-api.com/v4/latest/TND";

    private const SYMBOLS = [
        'TND' => 'TND',
        'EUR' => '€',
        'USD' => '$',
    ];

    private HttpClientInterface $httpClient;
    private CacheInterface $cache;

    public function __construct(HttpClientInterface $httpClient, CacheInterface $cache)
    {
        $this->httpClient = $httpClient;
        $this->cache = $cache;
    }

    /**
     * Récupère les taux de change depuis l'API externe avec mise en cache.
     */
    /**
     * @return array<string, float>
     */
    private function getRates(): array
    {
        return $this->cache->get('currency_rates_tnd', function (ItemInterface $item) {
            $item->expiresAfter(3600); // Cache d'une heure

            try {
                $response = $this->httpClient->request('GET', self::API_URL);
                $data = $response->toArray();
                return array_map('floatval', $data['rates'] ?? []);
            } catch (\Exception $e) {
                // En cas d'erreur API, on peut retourner des taux de secours ou vider le cache
                return [
                    'TND' => 1.0,
                    'EUR' => 0.30,
                    'USD' => 0.32,
                ];
            }
        });
    }

    #[Route('/marketplace/api/convert-currency', name: 'app_marketplace_api_convert_currency', methods: ['POST'])]
    public function convert(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['success' => false, 'message' => 'JSON invalide'], 400);
        }
        
        $amounts = $data['amounts'] ?? null;
        $amount = $data['amount'] ?? null;
        $targetCurrency = is_string($data['targetCurrency'] ?? null) ? $data['targetCurrency'] : 'TND';

        $rates = $this->getRates();

        if (!isset($rates[$targetCurrency])) {
            return new JsonResponse(['success' => false, 'message' => 'Devise non supportée'], 400);
        }

        $rate = $rates[$targetCurrency];
        $symbol = self::SYMBOLS[$targetCurrency] ?? $targetCurrency;

        if ($amounts !== null && is_array($amounts)) {
            $converted = array_map(function ($amt) use ($rate) {
                return round($amt * $rate, 2);
            }, array_map('floatval', $amounts));

            return new JsonResponse([
                'success' => true,
                'convertedAmounts' => $converted,
                'currency' => $targetCurrency,
                'symbol' => $symbol,
                'rate' => $rate
            ]);
        }

        if ($amount !== null) {
            $convertedAmount = (float) $amount * $rate;
            return new JsonResponse([
                'success' => true,
                'originalAmount' => $amount,
                'convertedAmount' => round($convertedAmount, 2),
                'currency' => $targetCurrency,
                'symbol' => $symbol,
                'formatted' => number_format($convertedAmount, 2, '.', ' ') . ' ' . $symbol
            ]);
        }

        return new JsonResponse(['success' => false, 'message' => 'Données manquantes (amount ou amounts)'], 400);
    }
}
