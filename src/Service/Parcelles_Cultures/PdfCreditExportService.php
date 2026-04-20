<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\CreditDossier;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Yaml\Yaml;

class PdfCreditExportService
{
    public function __construct(
        private TranslatorInterface $translator,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir
    ) {
    }

    /**
     * Exporte un dossier de crédit en PDF
     */
    public function exporterDossierCreditPdf(CreditDossier $dossier, string $outputPath = null, string $locale = 'fr'): string
    {
        // 🌡️ Pour l'Arabe, on utilise impérativement TCPDF (meilleure gestion RTL/Shaping)
        if ($locale === 'ar') {
            return $this->exporterAvecTCPDF($dossier, $outputPath, $locale);
        }

        // Pour les autres langues, on peut rester sur Dompdf
        return $this->exporterAvecDompdf($dossier, $outputPath, $locale);
    }

    private function exporterAvecDompdf(CreditDossier $dossier, string $outputPath = null, string $locale = 'fr'): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', realpath('.'));
        
        $dompdf = new Dompdf($options);
        $html = $this->genererHtmlDossier($dossier, $locale);
        
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfContent = $dompdf->output();

        if ($outputPath) {
            file_put_contents($outputPath, $pdfContent);
        }

        return $pdfContent;
    }

    private function exporterAvecTCPDF(CreditDossier $dossier, string $outputPath = null, string $locale = 'ar'): string
    {
        // Initialiser TCPDF
        // orientation, unit, format, unicode, encoding, diskcache
        $pdf = new \TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // Metadata
        $pdf->SetCreator('Ardhi');
        $pdf->SetAuthor('Ardhi AI');
        $pdf->SetTitle('Dossier de Crédit - ' . $dossier->getParcelle()->getLocalisation());

        // Configuration minimale
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(15, 15, 15);
        $pdf->SetAutoPageBreak(TRUE, 15);

        // 🛡️ Polices pour l'Arabe
        // dejavusans supporte parfaitement l'Arabe et est présent par défaut
        $pdf->SetFont('dejavusans', '', 12);

        // 🔄 Activer le mode RTL (Right-To-Left)
        $pdf->setRTL(true);

        $pdf->AddPage();

        // Générer le HTML
        $html = $this->genererHtmlDossier($dossier, $locale);
        
        // Écrire le HTML (TCPDF gère le shaping et l'alignement RTL automatiquement)
        $pdf->writeHTML($html, true, false, true, false, '');

        $pdfContent = $pdf->Output('', 'S');

        if ($outputPath) {
            file_put_contents($outputPath, $pdfContent);
        }

        return $pdfContent;
    }

    /**
     * Génère le HTML du dossier de crédit (traduit et optimisé)
     */
    private function genererHtmlDossier(CreditDossier $dossier, string $locale = 'fr'): string
    {
        $parcelle = $dossier->getParcelle();
        $agriculteur = $parcelle->getAgriculteur();
        
        $dateGenerer = date('d/m/Y H:i');
        $dateCreation = $dossier->getCreatedAt()->format('d/m/Y H:i');
        
        $niveauRisqueKey = match ($dossier->getNiveauRisque()) {
            'faible' => 'pdf.credit.risk_low',
            'modere' => 'pdf.credit.risk_moderate',
            'eleve' => 'pdf.credit.risk_high',
            default => 'pdf.credit.risk_moderate'
        };
        
        $recommandationsRaw = $dossier->getRecommandations();
        $recommandationsHtml = nl2br(htmlspecialchars($recommandationsRaw));

        $niveauRisqueColor = match ($dossier->getNiveauRisque()) {
            'faible' => '#28a745',
            'modere' => '#ffc107',
            'eleve' => '#dc3545',
            default => '#6c757d'
        };

        // Charger les traductions manuellement depuis le fichier YAML si nécessaire
        $translationFile = $this->projectDir . '/translations/messages.' . $locale . '.yaml';
        $translations = [];
        
        // Supprimer les traductions en dur si on veut utiliser uniquement le bundle
        if (file_exists($translationFile)) {
            $yaml = Yaml::parseFile($translationFile);
            if (is_array($yaml)) {
                // Aplatir les clés imbriquées
                $translations = $this->flattenArray($yaml);
            }
        }
        
        // Fonction pour obtenir les traductions avec fallback
        $t = function($key, $params = []) use ($translations, $locale) {
            if (isset($translations[$key])) {
                $value = $translations[$key];
                foreach ($params as $paramKey => $paramValue) {
                    $value = str_replace('%' . $paramKey . '%', $paramValue, $value);
                }
                return $value;
            }
            // Fallback: utiliser le translator Symfony
            return $this->translator->trans($key, $params, 'messages', $locale);
        };

        // 🔄 Traduire les recommandations dynamiquement (gestion des anciens textes et des nouveaux)
        $recommandationsTranslated = [];
        $lines = explode("\n", $recommandationsRaw);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) continue;
            
            $translatedLine = match($line) {
                // Match des textes en français (pour les anciens dossiers)
                "⚠️ RISQUE ÉLEVÉ: Il est recommandé de réduire le montant du crédit demandé." => $t('pdf.credit.reco_high_risk'),
                "⚠️ RISQUE MODÉRÉ: À surveiller étroitement." => $t('pdf.credit.reco_moderate_risk'),
                "✅ RISQUE FAIBLE: Bon candidat pour le crédit." => $t('pdf.credit.reco_low_risk'),
                "📊 Améliorer la rentabilité : considérez l'optimisation des coûts de production." => $t('pdf.credit.reco_improve_profitability'),
                // Match des clés (pour les futurs dossiers)
                "pdf.credit.reco_high_risk" => $t('pdf.credit.reco_high_risk'),
                "pdf.credit.reco_moderate_risk" => $t('pdf.credit.reco_moderate_risk'),
                "pdf.credit.reco_low_risk" => $t('pdf.credit.reco_low_risk'),
                "pdf.credit.reco_improve_profitability" => $t('pdf.credit.reco_improve_profitability'),
                default => $line
            };
            $recommandationsTranslated[] = $translatedLine;
        }
        $recommandationsHtml = nl2br(htmlspecialchars(implode("\n", $recommandationsTranslated)));

        $niveauRisqueStr = strtoupper($t($niveauRisqueKey));

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

        // Logo Ardhi - chargement du PNG
        $logoHtml = '';
        $logoPath = $this->projectDir . '/public/assets/img/ardhi_logo.png';
        if (file_exists($logoPath)) {
            try {
                $pngContent = file_get_contents($logoPath);
                $logoBase64 = 'data:image/png;base64,' . base64_encode($pngContent);
                $logoHtml = '<img src="' . $logoBase64 . '" alt="Ardhi Logo" style="max-height: 100px; max-width: 150px;">';
            } catch (\Exception $e) {
                // Logo non disponible - continuer sans
            }
        }

        $dir = $locale === 'ar' ? 'rtl' : 'ltr';
        $html = <<<HTML
<!DOCTYPE html>
<html dir="{$dir}" lang="{$locale}">
<head>
    <meta charset="UTF-8">
    <style>
        body { 
            font-family: 'dejavusans', 'Arial', sans-serif; 
            line-height: 1.6; 
            color: #333; 
        }
        .header-table {
            width: 100%;
            border-bottom: 3px solid #116530;
            margin-bottom: 20px;
        }
        .header-logo {
            width: 120px;
            padding: 10px;
        }
        .header-text {
            padding: 10px;
            vertical-align: middle;
        }
        .header-text h1 { 
            margin: 0; 
            font-size: 24px; 
            color: #116530;
        }
        .header-text p { 
            margin: 2px 0; 
            font-size: 11px; 
            color: #666;
        }
        .section { 
            margin-bottom: 25px; 
        }
        .section-title { 
            background-color: #116530;
            color: #ffffff; 
            padding: 8px 12px; 
            font-size: 14px;
            font-weight: bold;
        }
        .info-table {
            width: 100%;
            border-collapse: collapse;
        }
        .info-table td {
            padding: 8px;
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }
        .info-label {
            font-weight: bold;
            color: #116530;
            width: 40%;
        }
        .score-box { 
            background-color: #f0f9f0;
            border: 2px solid {$niveauRisqueColor};
            padding: 20px; 
            text-align: center; 
            margin: 15px 0;
        }
        .score-value { 
            font-size: 36px; 
            font-weight: bold; 
            color: {$niveauRisqueColor};
        }
        .data-table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 15px 0;
        }
        .data-table th { 
            background-color: #116530;
            color: #ffffff;
            padding: 10px; 
            font-size: 12px;
            text-align: center;
        }
        .data-table td { 
            padding: 8px; 
            border: 1px solid #ddd;
            font-size: 12px;
            text-align: center;
        }
        .recommendations { 
            background-color: #f8fff8;
            border-right: 5px solid #116530; 
            padding: 15px; 
            margin: 15px 0;
            font-size: 12px;
            color: #333;
        }
        .footer { 
            text-align: center; 
            margin-top: 30px; 
            font-size: 9px; 
            color: #999; 
            border-top: 1px solid #ddd; 
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <table class="header-table">
        <tr>
            <td class="header-logo">{$logoHtml}</td>
            <td class="header-text">
                <h1>{$t('pdf.credit.title')}</h1>
                <p>{$t('pdf.credit.subtitle')}</p>
                <p>{$t('pdf.credit.generated')} {$dateGenerer}</p>
            </td>
        </tr>
    </table>

    <div class="section">
        <div class="section-title">{$t('pdf.credit.section1')}</div>
        <table class="info-table">
            <tr>
                <td class="info-label">{$t('pdf.credit.farmer')}</td>
                <td>{$nomAgriculteur}</td>
            </tr>
            <tr>
                <td class="info-label">{$t('pdf.credit.email')}</td>
                <td>{$emailAgriculteur}</td>
            </tr>
            <tr>
                <td class="info-label">{$t('pdf.credit.location')}</td>
                <td>{$locParcelle} ({$surfParcelle} ha)</td>
            </tr>
            <tr>
                <td class="info-label">{$t('pdf.credit.duration')}</td>
                <td>{$duree} {$t('pdf.credit.years')}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{$t('pdf.credit.section2')}</div>
        <div class="score-box">
            <div style="font-size: 10px; text-transform: uppercase;">{$t('pdf.credit.global_risk')}</div>
            <div class="score-value">{$scoreRisque} / 10</div>
            <div style="font-weight: bold; color: {$niveauRisqueColor}">{$t('pdf.credit.level')} {$niveauRisqueStr}</div>
        </div>

        <table class="data-table">
            <thead>
                <tr>
                    <th>{$t('pdf.credit.criteria')}</th>
                    <th>{$t('pdf.credit.score')}</th>
                    <th>{$t('pdf.credit.weight')}</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td style="text-align: right;">{$t('pdf.credit.economic_profitability')}</td>
                    <td>{$scoreRentabilite}</td>
                    <td>40%</td>
                </tr>
                <tr>
                    <td style="text-align: right;">{$t('pdf.credit.climate_stability')}</td>
                    <td>{$scoreClimat}</td>
                    <td>30%</td>
                </tr>
                <tr>
                    <td style="text-align: right;">{$t('pdf.credit.diversification')}</td>
                    <td>{$scoreDiversification}</td>
                    <td>20%</td>
                </tr>
                <tr>
                    <td style="text-align: right;">{$t('pdf.credit.experience')}</td>
                    <td>{$scoreHistorique}</td>
                    <td>10%</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{$t('pdf.credit.section3')}</div>
        <table class="info-table">
            <tr>
                <td class="info-label">{$t('pdf.credit.repayment_capacity')}</td>
                <td><strong>{$capacite} {$t('pdf.credit.dt')}</strong></td>
            </tr>
            <tr>
                <td class="info-label">{$t('pdf.credit.max_loan')}</td>
                <td><strong>{$montantMax} {$t('pdf.credit.dt')}</strong></td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{$t('pdf.credit.section4')}</div>
        <div class="recommendations">
            {$recommandationsHtml}
        </div>
    </div>

    <div class="footer">
        <p>{$t('pdf.credit.recommendations')}</p>
        <p>{$t('pdf.credit.dossier_id')} #{$idDossier} | {$t('pdf.credit.creation_date')} {$dateCreation}</p>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Aplatit un tableau imbriqué avec des clés pointées
     * Ex: ['pdf' => ['credit' => ['farmer' => '...']]] -> ['pdf.credit.farmer' => '...']
     */
    private function flattenArray(array $array, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;

            if (is_array($value)) {
                $flattened = array_merge($flattened, $this->flattenArray($value, $newKey));
            } else {
                $flattened[$newKey] = $value;
            }
        }

        return $flattened;
    }
}

