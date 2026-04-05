<?php
require 'vendor/autoload.php';
use App\Service\EmployeTache\QrCodeService;
use Symfony\Component\HttpFoundation\RequestStack;

try {
    $qrCode = new \Endroid\QrCode\QrCode(data: 'test');
    $writer = new \Endroid\QrCode\Writer\SvgWriter();
    $svg = $writer->write($qrCode)->getString();
    
    $pdf = new \TCPDF();
    $pdf->AddPage();
    $pdf->ImageSVG('@' . $svg, 10, 10, 50, 50);
    echo "SUCCESS TCPDF SVG: " . strlen($pdf->Output('test.pdf', 'S'));
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
