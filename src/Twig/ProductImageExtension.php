<?php

namespace App\Twig;

use Symfony\Component\Asset\Packages;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ProductImageExtension extends AbstractExtension
{
    private Packages $assets;
    private UrlGeneratorInterface $router;

    public function __construct(Packages $assets, UrlGeneratorInterface $router)
    {
        $this->assets = $assets;
        $this->router = $router;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('product_image_url', [$this, 'productImageUrl']),
        ];
    }

    public function productImageUrl(?string $image): string
    {
        if (!$image) {
            return '';
        }

        if (preg_match('#^https?://#i', $image)) {
            return $image;
        }

        if ($this->isWindowsPath($image)) {
            return $this->router->generate('app_product_image_local', ['path' => $image]);
        }

        return $this->assets->getUrl('uploads/produits/' . ltrim($image, '/'));
    }

    private function isWindowsPath(string $image): bool
    {
        if (preg_match('#^[a-zA-Z]:\\\\#', $image)) {
            return true;
        }

        if (str_starts_with($image, '\\\\')) {
            return true;
        }

        return false;
    }
}
