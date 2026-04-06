<?php
require 'vendor/autoload.php';
use App\Service\EmployeTache\QrCodeService;
use Symfony\Component\HttpFoundation\RequestStack;

try {
    $s = new QrCodeService(new RequestStack());
    echo "URL that will be encoded: " . $s->generateFicheUrl(46) . "\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
