<?php

namespace App\Service\Evenement;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Fetches and downloads a relevant image from Unsplash
 * based on the event type — ported from the Java UnsplashImageService.
 */
class UnsplashImageService
{
    private const UNSPLASH_ACCESS_KEY = 'QLOSI6_S3p9kjO0uI8rKjw_4mpQzrzFgasED-jeQnfo';
    private const UNSPLASH_API_URL    = 'https://api.unsplash.com/search/photos';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface     $logger,
        private string              $projectDir   // injected via services.yaml bind
    ) {}

    /**
     * Search Unsplash for a photo matching the event type,
     * download it to public/uploads/evenements/ and return the web path.
     *
     * @return string|null  Web-accessible path like /uploads/evenements/unsplash_foire_xxx.jpg
     *                      or null on failure.
     */
    public function rechercherImage(string $type): ?string
    {
        $query = match ($type) {
            'FOIRE'      => 'agricultural fair farming market',
            'FORMATION'  => 'farming training agriculture education',
            'CONFERENCE' => 'agriculture conference business',
            'ATELIER'    => 'farm workshop hands-on',
            default      => 'agriculture farming',
        };

        try {
            $response = $this->httpClient->request('GET', self::UNSPLASH_API_URL, [
                'query' => [
                    'query'      => $query,
                    'per_page'   => 1,
                    'client_id'  => self::UNSPLASH_ACCESS_KEY,
                ],
            ]);

            $data = $response->toArray();

            if (empty($data['results'])) {
                $this->logger->warning('Unsplash: no results for type "{type}"', ['type' => $type]);
                return null;
            }

            $imageUrl = $data['results'][0]['urls']['regular'] ?? null;
            if (!$imageUrl) {
                return null;
            }

            return $this->downloadImage($imageUrl, $type);

        } catch (\Throwable $e) {
            $this->logger->error('Unsplash search error: {msg}', ['msg' => $e->getMessage()]);
            return null;
        }
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function downloadImage(string $imageUrl, string $type): ?string
    {
        $uploadsDir = $this->projectDir . '/public/uploads/evenements';
        if (!is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0755, true);
        }

        $fileName    = 'unsplash_' . strtolower($type) . '_' . time() . '.jpg';
        $destination = $uploadsDir . '/' . $fileName;

        try {
            $imageResponse = $this->httpClient->request('GET', $imageUrl);
            file_put_contents($destination, $imageResponse->getContent());

            $this->logger->info('Unsplash image downloaded: {file}', ['file' => $fileName]);

            return '/uploads/evenements/' . $fileName;

        } catch (\Throwable $e) {
            $this->logger->error('Unsplash download error: {msg}', ['msg' => $e->getMessage()]);
            return null;
        }
    }
}
