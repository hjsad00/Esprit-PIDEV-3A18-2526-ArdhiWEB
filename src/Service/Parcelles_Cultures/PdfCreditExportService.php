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
        
        $dateGenerer = date('d/m/Y H:i');
        $dateCreation = $dossier->getCreatedAt()->format('d/m/Y H:i');
        $niveauRisqueStr = strtoupper($dossier->getNiveauRisque());
        $recommandationsHtml = nl2br(htmlspecialchars($dossier->getRecommandations()));

        $niveauRisqueColor = match ($dossier->getNiveauRisque()) {
            'faible' => '#28a745',
            'modere' => '#ffc107',
            'eleve' => '#dc3545',
            default => '#6c757d'
        };

        $scoreRisque = $dossier->getScoreRisque();
        $scoreRentabilite = $dossier->getScoreRentabilite();
        $scoreClimat = $dossier->getScoreStabiliteClimat();
        $scoreDiversification = $dossier->getScoreDiversification();
        $scoreHistorique = $dossier->getScoreHistorique();
        $capacite = $dossier->getCapaciteRemboursement();
        $montantMax = $dossier->getMontantPretMax();
        $duree = $dossier->getDureeAnnees();
        $nomAgriculteur = $agriculteur->getPrenom() . ' ' . $agriculteur->getNom();
        $emailAgriculteur = $agriculteur->getEmail();
        $locParcelle = $parcelle->getLocalisation();
        $surfParcelle = $parcelle->getSurface();
        $idDossier = $dossier->getId();

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        .header { text-align: center; border-bottom: 3px solid #116530; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { margin: 0; color: #116530; }
        .header p { margin: 5px 0; color: #666; }
        .section { margin-bottom: 20px; page-break-inside: avoid; }
        .section h2 { background: #116530; color: white; padding: 10px; margin-top: 0; font-size: 18px; }
        .info-row { border-bottom: 1px solid #eee; padding: 8px 0; }
        .info-label { font-weight: bold; display: inline-block; width: 40%; }
        .info-value { display: inline-block; width: 55%; }
        .score-box { 
            background: {$niveauRisqueColor}; 
            color: white; 
            padding: 20px; 
            border-radius: 8px; 
            text-align: center; 
            margin: 15px 0;
        }
        .score-box .label { font-size: 14px; text-transform: uppercase; letter-spacing: 1px; }
        .score-box .value { font-size: 32px; font-weight: bold; margin: 10px 0; }
        .table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .table th { background: #f8f9fa; padding: 10px; text-align: left; border-bottom: 2px solid #ddd; }
        .table td { padding: 10px; border-bottom: 1px solid #eee; }
        .recommendations { background: #e7f3ff; border-left: 5px solid #0d6efd; padding: 15px; margin: 15px 0; font-style: italic; }
        .footer { text-align: center; margin-top: 40px; font-size: 11px; color: #888; border-top: 1px solid #ddd; padding-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>DOSSIER DE CRÉDIT AGRICOLE</h1>
        <p>Analyse de Viabilité Financière et Score de Risque</p>
        <p>Document officiel - Généré le: {$dateGenerer}</p>
    </div>

    <div class="section">
        <h2>1. Informations du Demandeur</h2>
        <div class="info-row">
            <span class="info-label">Agriculteur:</span>
            <span class="info-value">{$nomAgriculteur}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Email de contact:</span>
            <span class="info-value">{$emailAgriculteur}</span>
        </div>
        <div class="info-row">
            <span class="info-label">Localisation Parcelle:</span>
            <span class="info-value">{$locParcelle} ({$surfParcelle} ha)</span>
        </div>
        <div class="info-row">
            <span class="info-label">Durée demandée:</span>
            <span class="info-value">{$duree} ans</span>
        </div>
    </div>

    <div class="section">
        <h2>2. Évaluation de la Crédibilité (Score Risque)</h2>
        <div class="score-box">
            <div class="label">Indice de Risque Global</div>
            <div class="value">{$scoreRisque} / 10</div>
            <div class="label" style="font-weight: bold;">NIVEAU: {$niveauRisqueStr}</div>
        </div>

        <table class="table">
            <thead>
                <tr>
                    <th>Critère d'Analyse</th>
                    <th>Score / 10</th>
                    <th>Pondération</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Rentabilité Économique</td>
                    <td>{$scoreRentabilite}</td>
                    <td>40%</td>
                </tr>
                <tr>
                    <td>Stabilité des Rendements (Climat)</td>
                    <td>{$scoreClimat}</td>
                    <td>30%</td>
                </tr>
                <tr>
                    <td>Diversification des Cultures</td>
                    <td>{$scoreDiversification}</td>
                    <td>20%</td>
                </tr>
                <tr>
                    <td>Historique et Expérience</td>
                    <td>{$scoreHistorique}</td>
                    <td>10%</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>3. Analyse de la Capacité Financière</h2>
        <div class="info-row">
            <span class="info-label">Capacité de Remboursement Annuelle:</span>
            <span class="info-value"><strong>{$capacite} DT</strong></span>
        </div>
        <div class="info-row">
            <span class="info-label">Plafond Maximum du Prêt:</span>
            <span class="info-value"><strong>{$montantMax} DT</strong></span>
        </div>
    </div>

    <div class="section">
        <h2>4. Recommandations Ardhi</h2>
        <div class="recommendations">
            {$recommandationsHtml}
        </div>
    </div>

    <div class="footer">
        <p>Ce rapport est généré par le moteur d'analyse Ardhi sur la base des données fournies.</p>
        <p>Identifiant Dossier: #{$idDossier} | Date de création: {$dateCreation}</p>
    </div>
</body>
</html>
HTML;

        return $html;
    }
}
