<?php

namespace App\Service\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class QrCodeService
{
    private UrlGeneratorInterface $urlGenerator;
    private string $projectDir;

    public function __construct(UrlGeneratorInterface $urlGenerator, string $projectDir)
    {
        $this->urlGenerator = $urlGenerator;
        $this->projectDir = $projectDir;
    }

    /**
     * Génère un QR Code pour un matériel donné.
     * Le QR Code pointe vers la fiche mobile publique.
     */
    public function generateForMateriel(Materiel $materiel): string
    {
        $token = $materiel->getQrCodeToken();
        
        // On récupère l'URL de base depuis le .env (pour forcer Ngrok même en local)
        $baseUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        $path = $this->urlGenerator->generate('app_mobile_machine_show', [
            'token' => $token
        ], UrlGeneratorInterface::ABSOLUTE_PATH);

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/');

        // Version 6 : Instanciation directe du Builder avec arguments nommés
        // On utilise SvgWriter car il ne dépend pas de l'extension GD
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

        // Nom du fichier basé sur le token (extension .svg)
        $fileName = 'qr-' . $token . '.svg';
        $directory = $this->projectDir . '/public/uploads/qrcodes/';

        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $path = $directory . $fileName;
        $result->saveToFile($path);

        // Retourne le chemin relatif pour l'affichage
        return 'uploads/qrcodes/' . $fileName;
    }
}
