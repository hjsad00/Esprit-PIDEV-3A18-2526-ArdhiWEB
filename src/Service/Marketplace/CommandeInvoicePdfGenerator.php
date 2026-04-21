<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Commande;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class CommandeInvoicePdfGenerator
{
    public function __construct(
        private Environment $twig,
    ) {}

    public function generate(Commande $commande, array $productQrCodes): string
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true); // Autorise les images externes / liées
        $pdfOptions->set('isPhpEnabled', true);    // Requis pour <script type="text/php"> (numérotation des pages)

        $dompdf = new Dompdf($pdfOptions);

        $html = $this->twig->render('Marketplace/pdf_facture.html.twig', [
            'commande' => $commande,
            'productQrCodes' => $productQrCodes,
        ]);

        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
