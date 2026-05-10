<?php

namespace App\Controller\Marketplace;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\Routing\Annotation\Route;

class ProductImageController extends AbstractController
{
    private string $productImagesDir;

    public function __construct(string $productImagesDir)
    {
        $this->productImagesDir = $productImagesDir;
    }

    #[Route('/local-image', name: 'app_product_image_local', methods: ['GET'])]
    public function localImage(Request $request): Response
    {
        $path = (string) $request->query->get('path', '');
        if ($path === '') {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $baseDir = rtrim($this->productImagesDir, "\\/");
        $realBase = realpath($baseDir);
        $realPath = realpath($path);

        if ($realBase === false || $realPath === false) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $realBaseNormalized = strtolower($realBase . DIRECTORY_SEPARATOR);
        $realPathNormalized = strtolower($realPath);

        if (strpos($realPathNormalized, $realBaseNormalized) !== 0 || !is_file($realPath)) {
            return new Response('', Response::HTTP_NOT_FOUND);
        }

        $response = new BinaryFileResponse($realPath);
        $mimeTypes = new MimeTypes();
        $mime = $mimeTypes->guessMimeType($realPath);

        if ($mime) {
            $response->headers->set('Content-Type', $mime);
        }

        $response->setContentDisposition(
            ResponseHeaderBag::DISPOSITION_INLINE,
            basename($realPath)
        );

        return $response;
    }
}
