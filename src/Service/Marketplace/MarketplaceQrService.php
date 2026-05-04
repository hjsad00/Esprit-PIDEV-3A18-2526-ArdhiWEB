<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\Marketplace\Produits;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class MarketplaceQrService
{
    private UrlGeneratorInterface $urlGenerator;
    private string $projectDir;

    public function __construct(UrlGeneratorInterface $urlGenerator, string $projectDir)
    {
        $this->urlGenerator = $urlGenerator;
        $this->projectDir = $projectDir;
    }

    /**
     * Méthode générique pour générer un QR Code et le sauvegarder.
     */
    public function generateAndSave(string $url, string $fileName): string
    {
        $builder = new Builder(
            writer: new SvgWriter(),
            data: $url,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 300,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin
        );

        $result = $builder->build();

        // Dossier dédié au marketplace
        $directory = $this->projectDir . '/public/uploads/qrcodes/marketplace/';
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory . $fileName;
        $result->saveToFile($path);

        return 'uploads/qrcodes/marketplace/' . $fileName;
    }

    /**
     * Génère le QR Code pour une commande (Redirection vers le résumé scan).
     */
    public function generateForOrder(Commande $commande): string
    {
        $token = $commande->getQrCodeToken();
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        
        $path = $this->urlGenerator->generate('app_marketplace_commande_scan', [
            'token' => $token
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        
        return $this->generateAndSave($url, 'order-' . $token . '.svg');
    }

    /**
     * Génère le QR Code pour un produit (Redirection vers la fiche produit).
     */
    public function generateForProduct(Produits $produit): string
    {
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        
        $path = $this->urlGenerator->generate('app_marketplace_produit_show', [
            'id' => $produit->getId()
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        
        return $this->generateAndSave($url, 'product-' . $produit->getId() . '.svg');
    }
}
