<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\CreditDossier;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Service pour l'export PDF des dossiers crédit
 */
class PdfCreditExportService
{
    private $projectDir;

    public function __construct(string $projectDir)
    {
        $this->projectDir = $projectDir;
    }

    /**
     * Exporte un dossier crédit en PDF
     */
    public function exporterDossierCreditPdf(CreditDossier $dossier): string
    {
        $html = $this->genererHtml($dossier);
        $filename = $this->sauvegarderDossierPdf($dossier, $html);
        return $filename;
    }

    /**
     * Exporte un dossier crédit en PDF (retourne le contenu en stream)
     */
    public function exporterDossierCreditPdfStream(CreditDossier $dossier): string
    {
        $html = $this->genererHtml($dossier);
        
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', true);
        
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }

    /**
     * Génère le HTML du dossier credit
     */
    public function genererHtml(CreditDossier $dossier): string
    {
        $parcelle = $dossier->getParcelle();
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Dossier Crédit Agricole</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
        h1 { color: #2c3e50; border-bottom: 3px solid #3498db; padding-bottom: 10px; }
        h2 { color: #34495e; margin-top: 20px; border-left: 5px solid #3498db; padding-left: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th { background-color: #3498db; color: white; padding: 10px; text-align: left; }
        td { border: 1px solid #bdc3c7; padding: 10px; }
        tr:nth-child(even) { background-color: #ecf0f1; }
        .score-badge { display: inline-block; padding: 5px 10px; border-radius: 5px; font-weight: bold; }
        .score-good { background-color: #27ae60; color: white; }
        .score-medium { background-color: #f39c12; color: white; }
        .score-bad { background-color: #e74c3c; color: white; }
        .footer { margin-top: 30px; font-size: 10px; color: #7f8c8d; border-top: 1px solid #bdc3c7; padding-top: 10px; }
    </style>
</head>
<body>
    <h1>DOSSIER CRÉDIT AGRICOLE</h1>
    <p><strong>Date:</strong> {$dossier->getDateCreation()->format('d/m/Y')}</p>
    <p><strong>Référence:</strong> CREDIT-{$dossier->getId()}</p>

    <h2>1. INFORMATIONS PARCELLE</h2>
    <table>
        <tr><td><strong>Surface:</strong></td><td>{$parcelle->getSurface()} ha</td></tr>
        <tr><td><strong>Localisation:</strong></td><td>{$parcelle->getLocalisation()}</td></tr>
        <tr><td><strong>Type de Sol:</strong></td><td>{$parcelle->getTypeSol()}</td></tr>
        <tr><td><strong>Système d'irrigation:</strong></td><td>{$parcelle->getSystemeIrrigation()}</td></tr>
        <tr><td><strong>Statut:</strong></td><td>{$parcelle->getStatut()}</td></tr>
    </table>

    <h2>2. SCORES D'ANALYSE</h2>
    <table>
        <tr>
            <th>Critère</th>
            <th>Score</th>
            <th>Classement</th>
        </tr>
        <tr>
            <td>Rentabilité</td>
            <td>{$dossier->getScoreRentabilite()}/100</td>
            <td>{$this->getBadgeScore($dossier->getScoreRentabilite())}</td>
        </tr>
        <tr>
            <td>Stabilité Climatique</td>
            <td>{$dossier->getScoreStabiliteClimat()}/100</td>
            <td>{$this->getBadgeScore($dossier->getScoreStabiliteClimat())}</td>
        </tr>
        <tr>
            <td>Diversification</td>
            <td>{$dossier->getScoreDiversification()}/100</td>
            <td>{$this->getBadgeScore($dossier->getScoreDiversification())}</td>
        </tr>
        <tr>
            <td>Historique</td>
            <td>{$dossier->getScoreHistorique()}/100</td>
            <td>{$this->getBadgeScore($dossier->getScoreHistorique())}</td>
        </tr>
    </table>

    <h2>3. ÉVALUATION DE RISQUE</h2>
    <table>
        <tr><td><strong>Score de Risque:</strong></td><td>{$dossier->getScoreRisque()}/100</td></tr>
        <tr><td><strong>Niveau de Risque:</strong></td><td>{$dossier->getNiveauRisque()}</td></tr>
        <tr><td><strong>Durée du Crédit:</strong></td><td>{$dossier->getDureeAnnees()} ans</td></tr>
    </table>

    <h2>4. CAPACITÉ FINANCIÈRE</h2>
    <table>
        <tr><td><strong>Capacité de Remboursement Annuelle:</strong></td><td>{$dossier->getCapaciteRemboursement()} €</td></tr>
        <tr><td><strong>Montant Prêt Maximum Autorisé:</strong></td><td>{$dossier->getMontantPretMax()} €</td></tr>
    </table>

    <div class="footer">
        <p>Ce dossier a été généré automatiquement par le système d'analyse de crédit agricole.</p>
        <p>Il est confidentiel et réservé à un usage recommandé.</p>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Sauvegarde le PDF généré
     */
    public function sauvegarderDossierPdf(CreditDossier $dossier, string $html): string
    {
        $pdfDir = $this->projectDir . '/public/pdf_credits';
        if (!is_dir($pdfDir)) {
            mkdir($pdfDir, 0755, true);
        }

        $filename = 'credit_' . $dossier->getId() . '_' . uniqid() . '.pdf';
        $filepath = $pdfDir . '/' . $filename;

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isPhpEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        file_put_contents($filepath, $dompdf->output());

        $dossier->setDateExport(new \DateTime());

        return $filename;
    }

    /**
     * Retourne le badge HTML pour un score
     */
    private function getBadgeScore(float $score): string
    {
        if ($score >= 70) {
            $class = 'score-good';
            $label = 'Bon';
        } elseif ($score >= 40) {
            $class = 'score-medium';
            $label = 'Moyen';
        } else {
            $class = 'score-bad';
            $label = 'Faible';
        }

        return "<span class=\"score-badge $class\">$label</span>";
    }
}
