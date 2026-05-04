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
        // 1. Récupérer l'IP locale (LAN) - Utiliser l'env APP_LAN_IP si défini, sinon détecter.
        $hostname = gethostname();
        $ip = $_ENV['APP_LAN_IP'] ?? ($hostname !== false ? gethostbyname($hostname) : '127.0.0.1');
        
        // 2. Générer l'URL absolue canonique via Symfony
        $url = $this->router->generate('employe_fiche', ['id' => $employeId], UrlGeneratorInterface::ABSOLUTE_URL);
        
        // 3. Remplacer localhost par l'IP réseau
        $request = $this->requestStack->getCurrentRequest();
        $httpHost = $request ? $request->getHttpHost() : 'localhost';
        
        if ($ip !== '127.0.0.1' && $ip !== false) {
            $newHttpHost = str_replace($request ? $request->getHost() : 'localhost', $ip, $httpHost);
            $url = str_replace('://' . $httpHost, '://' . $newHttpHost, $url);
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
