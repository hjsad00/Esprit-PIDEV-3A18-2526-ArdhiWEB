<?php

namespace App\Service\UserAndDiag;

use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImgBBService
{
    private const API_KEY = '99d0529ec6a3085b1b676ad6b7b9fa5f';
    private const UPLOAD_URL = 'https://api.imgbb.com/1/upload';

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
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                'key' => self::API_KEY,
                'image' => $base64,
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200 || !$response) {
                return null;
            }

            $data = json_decode($response, true);

            return $data['data']['url'] ?? null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
