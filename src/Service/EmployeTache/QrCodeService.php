<?php

namespace App\Service\EmployeTache;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\HttpFoundation\RequestStack;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class QrCodeService
{
    private RequestStack $requestStack;
    private UrlGeneratorInterface $router;

    public function __construct(RequestStack $requestStack, UrlGeneratorInterface $router)
    {
        $this->requestStack = $requestStack;
        $this->router = $router;
    }

    /**
     * Génère l'URL locale (LAN IP + port + base path) pour le QR code
     */
    public function generateFicheUrl(int $employeId): string
    {
        // 1. Récupérer l'IP locale (LAN) de la machine serveur
        $ip = gethostbyname(gethostname());
        
        // 2. Générer l'URL absolue canonique via Symfony (gère le port et les sous-dossiers XAMPP)
        $url = $this->router->generate('employe_fiche', ['id' => $employeId], UrlGeneratorInterface::ABSOLUTE_URL);
        
        // 3. Remplacer le domaine local (localhost, 127.0.0.1) par l'IP réseau.
        $request = $this->requestStack->getCurrentRequest();
        $host = $request ? $request->getHost() : 'localhost';
        
        if ($ip !== '127.0.0.1' && $ip !== false) {
            $url = str_replace('://' . $host, '://' . $ip, $url);
        }

        return $url;
    }

    /**
     * Génère un QR Code sous forme vectorielle (SVG).
     */
    public function generateQrCodeSvg(string $data, int $size = 200): string
    {
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 1
        );

        $writer = new SvgWriter();
        return $writer->write($qrCode)->getString();
    }

    /**
     * Génère un QR Code sous forme de Data URI (Base64).
     * Idéal pour l'afficher directement dans un template HTML/Twig avec <img src="{{ data_uri }}">
     */
    public function generateQrCodeDataUri(string $data, int $size = 200): string
    {
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: $size,
            margin: 1
        );

        $writer = new SvgWriter();
        return $writer->write($qrCode)->getDataUri();
    }
}
