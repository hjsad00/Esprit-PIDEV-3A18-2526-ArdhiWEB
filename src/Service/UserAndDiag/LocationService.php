<?php

namespace App\Service\UserAndDiag;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class LocationService
{
    private const API_URL = 'http://ip-api.com/json/%s?fields=status,lat,lon,city,regionName,country';

    private HttpClientInterface $client;
    private LoggerInterface $logger;

    public function __construct(HttpClientInterface $client, LoggerInterface $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
    }

    /**
     * Detects location based on an IP address.
     * Uses ip-api.com free service just like the original Java app.
     */
    public function detectLocation(?string $ip = null): ?array
    {
        try {
            // If running strictly on localhost/127.0.0.1, we omit the IP to let the API ping our external network IP cleanly
            $targetIp = ($ip && $ip !== '127.0.0.1' && $ip !== '::1') ? $ip : '';

            $url = sprintf(self::API_URL, $targetIp);

            $response = $this->client->request('GET', $url, [
                'timeout' => 5,
            ]);

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();

                if (isset($data['status']) && $data['status'] === 'success') {
                    $lat = $data['lat'] ?? null;
                    $lon = $data['lon'] ?? null;

                    if ($lat !== null && $lon !== null) {
                        $labelParts = [];
                        if (!empty($data['city']))
                            $labelParts[] = $data['city'];
                        if (!empty($data['regionName']))
                            $labelParts[] = $data['regionName'];
                        if (!empty($data['country']))
                            $labelParts[] = $data['country'];

                        return [
                            'latitude' => (float) $lat,
                            'longitude' => (float) $lon,
                            'label' => implode(', ', $labelParts)
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error("LocationService failed to detect location: " . $e->getMessage());
        }

        return null;
    }
}
