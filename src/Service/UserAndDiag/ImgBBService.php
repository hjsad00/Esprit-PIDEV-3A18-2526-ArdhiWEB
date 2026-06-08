<?php

namespace App\Service\UserAndDiag;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImgBBService
{
    private string $apiKey;
    private const UPLOAD_URL = 'https://api.imgbb.com/1/upload';

    public function __construct(string $imgbbApiKey)
    {
        $this->apiKey = $imgbbApiKey;
    }

    /**
     * Uploads an image file to ImgBB and returns the direct display URL.
     *
     * @param UploadedFile $file The uploaded file to send to ImgBB
     * @return string|null The direct URL of the uploaded image, or null if upload fails
     */
    public function uploadImage(UploadedFile $file): ?string
    {
        try {
            $base64 = base64_encode(file_get_contents($file->getPathname()));

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::UPLOAD_URL);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'key' => $this->apiKey,
                'image' => $base64,
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 60); // Increase timeout

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                error_log("ImgBB Upload Failed: HTTP $httpCode - $curlError - Response: $response");
                return null;
            }

            $data = json_decode($response, true);
            return $data['data']['url'] ?? null;
        } catch (\Exception $e) {
            error_log("ImgBB Exception: " . $e->getMessage());
            return null;
        }
    }
}
