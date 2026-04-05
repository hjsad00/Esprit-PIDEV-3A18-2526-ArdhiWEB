<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\CreditDossier;
use Dompdf\Dompdf;
use Dompdf\Options;

class PdfCreditExportService
{
    /**
     * Exporte un dossier de crédit en PDF
     */
    public function exporterDossierCreditPdf(CreditDossier $dossier, string $outputPath = null): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', realpath('.'));

        $dompdf = new Dompdf($options);

        $html = $this->genererHtmlDossier($dossier);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf(
            'credit_dossier_%d_%s.pdf',
            $dossier->getId() ?? time(),
            date('YmdHis')
        );

        if ($outputPath) {
            $dompdf->stream($filename, ['Attachment' => false]);
        }

        return $dompdf->output();
    }

    /**
     * Génère le HTML du dossier de crédit
     */
    private function genererHtmlDossier(CreditDossier $dossier): string
    {
        $parcelle = $dossier->getParcelle();
        $agriculteur = $parcelle->getAgriculteur();

        $niveauRisqueColor = match ($dossier->getNiveauRisque()) {
            'faible' => '#28a745',
            'modere' => '#ffc107',
            'eleve' => '#dc3545',
            default => '#6c757d'
        };

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { text-align: center; border-bottom: 3px solid #116530; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #116530; }
        .header p { margin: 5px 0; color: #666; }
        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .section h2 { background: #116530; color: white; padding: 10px; margin-top: 0; }
        .info-row { display: flex; justify-content: space-between; border-bottom: 1px solid #eee; padding: 8px 0; }
        .info-label { font-weight: bold; width: 40%; }
        .info-value { width: 60%; }
        .score-box { 
            background: $niveauRisqueColor; 
            color: white; 
            padding: 15px; 
            border-radius: 5px; 
            text-align: center; 
            margin: 10px 0;
        }
        .score-box .label { font-size: 12px; }
        .score-box .value { font-size: 28px; font-weight: bold; }
        .table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        .table th { background: #f5f5f5; padding: 8px; text-align: left; border-bottom: 2px solid #ddd; }
        .table td { padding: 8px; border-bottom: 1px solid #eee; }
        .recommendations { background: #fff3cd; border-left: 4px solid #ffc107; padding: 10px; margin: 10px 0; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #666; border-top: 1px solid #ddd; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>Dossier de Crédit Agricole</h1>
        <p>Analyse de Rentabilité et Évaluation du Risque</p>
        <p>Généré le: " . date('d/m/Y H:i') . "</p>
    </div>

    <div class="section">
        <h2>Informations Générales</h2>
        <div class="info-row">
            <div class="info-label">Agriculteur:</div>
            <div class="info-value">{$agriculteur->getPrenom()} {$agriculteur->getNom()}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div class="info-value">{$agriculteur->getEmail()}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Parcelle:</div>
            <div class="info-value">{$parcelle->getLocalisation()} ({$parcelle->getSurface()} ha)</div>
        </div>
        <div class="info-row">
            <div class="info-label">Durée du Crédit:</div>
            <div class="info-value">{$dossier->getDureeAnnees()} ans</div>
        </div>
    </div>

    <div class="section">
        <h2>Évaluation du Risque</h2>
        <div class="score-box">
            <div class="label">Score de Risque Global</div>
            <div class="value">{$dossier->getScoreRisque()}/10</div>
            <div class="label" style="margin-top: 10px;">Niveau: " . strtoupper($dossier->getNiveauRisque()) . "</div>
        </div>

        <table class="table">
            <tr>
                <th>Critère</th>
                <th>Score</th>
                <th>Poids</th>
            </tr>
            <tr>
                <td>Rentabilité</td>
                <td>{$dossier->getScoreRentabilite()}/10</td>
                <td>40%</td>
            </tr>
            <tr>
                <td>Stabilité Climatique</td>
                <td>{$dossier->getScoreStabiliteClimat()}/10</td>
                <td>30%</td>
            </tr>
            <tr>
                <td>Diversification</td>
                <td>{$dossier->getScoreDiversification()}/10</td>
                <td>20%</td>
            </tr>
            <tr>
                <td>Historique</td>
                <td>{$dossier->getScoreHistorique()}/10</td>
                <td>10%</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2>Capacité de Remboursement</h2>
        <div class="info-row">
            <div class="info-label">Capacité Annuelle:</div>
            <div class="info-value">{$dossier->getCapaciteRemboursement()} €</div>
        </div>
        <div class="info-row">
            <div class="info-label">Montant Maximum du Prêt:</div>
            <div class="info-value">{$dossier->getMontantPretMax()} €</div>
        </div>
    </div>

    <div class="section">
        <h2>Recommandations</h2>
        <div class="recommendations">
            " . nl2br(htmlspecialchars($dossier->getRecommandations())) . "
        </div>
    </div>

    <div class="footer">
        <p>Ce document est généré automatiquement par le système Ardhi.</p>
        <p>Date de création: " . $dossier->getCreatedAt()->format('d/m/Y H:i') . "</p>
    </div>
</body>
</html>
HTML;

        return $html;
    }
}
