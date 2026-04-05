<?php

namespace App\Service\EmployeTache;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Symfony\Component\HttpFoundation\RequestStack;

class QrCodeService
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Génère l'URL locale (LAN IP + port) pour le QR code
     * "Comme le desktop Java" (qui utilisait un Server HTTP embarqué sur IP réseau).
     */
    public function generateFicheUrl(int $employeId): string
    {
        // 1. Récupérer l'IP locale (LAN) de la machine serveur
        $ip = gethostbyname(gethostname());
        if ($ip === '127.0.0.1' || $ip === '127.0.1.1' || $ip === false) {
            // Dans certains cas sous Windows, gethostbyname(gethostname()) renvoie 127.0.0.1
            // Symfony server tourne généralement sur toutes les IPs si lancé avec `symfony server:start`
            // Essayons de récupérer l'IP via d'autres moyens si possible
        }

        // 2. Récupérer le port actuel (généralement 8000 avec le CLI Symfony)
        $request = $this->requestStack->getCurrentRequest();
        $port = $request ? $request->getPort() : 8000;

        // Si l'utilisateur a tapé localhost ou 127.0.0.1 pour accéder à l'app web,
        // $request->getHost() sera "localhost". On utilise notre $ip LAN pour le téléphone.
        return "http://" . $ip . ":" . $port . "/employes/" . $employeId . "/fiche";
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
