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
     * Utilise DomPDF pour la génération PDF (pur PHP, sans dépendances externes)
     */
    public function exporterDossierCreditPdf(CreditDossier $dossier, string $outputPath = null, string $locale = 'fr'): string
    {
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', realpath('.'));
        $options->set('enablePhp', false);
        $options->set('tempDir', sys_get_temp_dir());
        $options->set('fontDir', sys_get_temp_dir());
        $options->set('logOutputFile', sys_get_temp_dir() . '/dompdf.log');

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

    /**
     * Génère le HTML du dossier de crédit (traduit et optimisé)
     */
    private function genererHtmlDossier(CreditDossier $dossier, string $locale = 'fr'): string
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

        // Charger les traductions manuellement depuis le fichier YAML
        $translationFile = $this->projectDir . '/translations/messages.' . $locale . '.yaml';
        $translations = [];
        
        // Traductions arabes en dur pour tester
        $arabicTranslations = [
            'pdf.credit.title' => 'طلب قرض زراعي',
            'pdf.credit.subtitle' => 'تحليل الجدوى المالية وتقييم درجة المخاطرة',
            'pdf.credit.generated' => 'وثيقة رسمية - تاريخ الإصدار:',
            'pdf.credit.section1' => '١. معلومات مقدم الطلب',
            'pdf.credit.section2' => '٢. تقييم الجدارة الائتمانية (درجة المخاطرة)',
            'pdf.credit.section3' => '٣. تحليل القدرة على السداد',
            'pdf.credit.section4' => '٤. توصيات أردهي',
            'pdf.credit.farmer' => 'المزارع:',
            'pdf.credit.email' => 'البريد الإلكتروني للتواصل:',
            'pdf.credit.location' => 'موقع الأرض:',
            'pdf.credit.duration' => 'مدة القرض المطلوبة:',
            'pdf.credit.years' => 'سنة',
            'pdf.credit.global_risk' => 'مؤشر درجة المخاطرة العام',
            'pdf.credit.level' => 'المستوى:',
            'pdf.credit.criteria' => 'معايير التحليل',
            'pdf.credit.score' => 'النقاط / ١٠',
            'pdf.credit.weight' => 'النسبة المئوية',
            'pdf.credit.economic_profitability' => 'الربحية الاقتصادية',
            'pdf.credit.climate_stability' => 'استقرار الإنتاج (العوامل المناخية)',
            'pdf.credit.diversification' => 'تنويع أنواع المحاصيل',
            'pdf.credit.experience' => 'الخبرة والسجل التاريخي',
            'pdf.credit.repayment_capacity' => 'الحد الأدنى للقدرة على السداد السنوي:',
            'pdf.credit.max_loan' => 'الحد الأقصى المقترح للقرض:',
            'pdf.credit.dt' => 'دينار',
            'pdf.credit.recommendations' => 'يتم إنشاء هذا التقرير بواسطة محرك تحليل أردهي بناءً على البيانات المقدمة.',
            'pdf.credit.dossier_id' => 'معرف ملف الدعوة:',
            'pdf.credit.creation_date' => 'تاريخ الإنشاء:',
        ];
        
        // Si c'est l'arabe, utiliser les traductions en dur
        if ($locale === 'ar') {
            $translations = $arabicTranslations;
        } elseif (file_exists($translationFile)) {
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

        $html = <<<HTML
<!DOCTYPE html>
<html dir="{($locale === 'ar' ? 'rtl' : 'ltr')}" lang="{$locale}">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <style>
        * { margin: 0; padding: 0; }
        body { 
            font-family: 'Arial', 'DejaVu Sans', sans-serif; 
            line-height: 1.8; 
            color: #333; 
            background: #ffffff;
            padding: 0;
            font-size: 14px;
        }
        body[dir="rtl"] { text-align: right; direction: rtl; }
        body[dir="ltr"] { text-align: left; direction: ltr; }
        .content { margin: 0; padding: 20px; }
        .page-break { page-break-after: always; }
        .header { 
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 20px;
            padding: 20px;
            margin-bottom: 30px;
            border-bottom: 3px solid #116530;
            flex-direction: row;
        }
        html[dir="rtl"] .header { flex-direction: row-reverse; justify-content: flex-end; }
        .header img { 
            max-height: 80px; 
            max-width: 120px; 
            flex-shrink: 0;
        }
        .header-text { 
            flex: 1; 
        }
        .header-text h1 { 
            margin: 0; 
            font-size: 26px; 
            font-weight: bold;
            color: #116530;
            text-align: left;
        }
        html[dir="rtl"] .header-text h1 { text-align: right; }
        .header-text p { 
            margin: 5px 0 0 0; 
            font-size: 12px; 
            color: #666;
            text-align: left;
        }
        html[dir="rtl"] .header-text p { text-align: right; }
        .section { 
            margin-bottom: 30px; 
            page-break-inside: avoid;
        }
        .section h2 { 
            background: #116530;
            color: white; 
            padding: 12px 15px; 
            margin: 0 0 15px 0; 
            font-size: 15px;
            font-weight: bold;
            border-radius: 3px;
            text-align: left;
        }
        html[dir="rtl"] .section h2 { text-align: right; }
        .info-row { 
            border-bottom: 1px solid #eee; 
            padding: 10px 0; 
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-direction: row-reverse;
        }
        html[dir="ltr"] .info-row { flex-direction: row; }
        html[dir="rtl"] .info-row { flex-direction: row-reverse; }
        .info-row:last-child { border-bottom: none; }
        .info-label { 
            font-weight: 600; 
            width: 40%;
            color: #116530;
            font-size: 12px;
        }
        .info-value { 
            width: 55%; 
            text-align: right;
            color: #333;
            font-size: 12px;
        }
        html[dir="rtl"] .info-value { text-align: left; }
        .score-box { 
            background: #f0f9f0;
            border: 2px solid {$niveauRisqueColor};
            border-left: 8px solid {$niveauRisqueColor};
            color: {$niveauRisqueColor};
            padding: 30px; 
            border-radius: 5px; 
            text-align: center; 
            margin: 25px 0;
        }
        html[dir="rtl"] .score-box { border-left: none; border-right: 8px solid {$niveauRisqueColor}; }
        .score-box .label { 
            font-size: 11px; 
            text-transform: uppercase; 
            letter-spacing: 1px;
            font-weight: 600;
            color: #116530;
        }
        .score-box .value { 
            font-size: 42px; 
            font-weight: bold; 
            margin: 12px 0;
            color: {$niveauRisqueColor};
        }
        .table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0;
            border: 1px solid #ddd;
        }
        .table th { 
            background: #116530;
            color: white;
            padding: 12px; 
            text-align: left; 
            font-weight: 600;
            font-size: 12px;
        }
        html[dir="rtl"] .table th { text-align: right; }
        .table td { 
            padding: 10px 12px; 
            border-bottom: 1px solid #eee;
            font-size: 12px;
        }
        html[dir="rtl"] .table td { text-align: right; }
        .table tr:nth-child(even) { background: #f9f9f9; }
        .recommendations { 
            background: #f0f9f0;
            border-left: 5px solid #116530; 
            padding: 15px 20px; 
            margin: 20px 0;
            font-size: 12px;
            color: #333;
            border-radius: 3px;
        }
        html[dir="rtl"] .recommendations { border-left: none; border-right: 5px solid #116530; }
        .recommendations strong { color: #116530; }
        .footer { 
            text-align: center; 
            margin-top: 40px; 
            font-size: 10px; 
            color: #999; 
            border-top: 1px solid #ddd; 
            padding-top: 15px;
        }
        .content { margin: 0 20px; }
    </style>
</head>
<body>
    <div class="header">
        {$logoHtml}
        <div class="header-text">
            <h1>{$t('pdf.credit.title')}</h1>
            <p>{$t('pdf.credit.subtitle')}</p>
            <p>{$t('pdf.credit.generated')} {$dateGenerer}</p>
        </div>
    </div>

    <div class="content">
        <div class="section">
            <h2>{$t('pdf.credit.section1')}</h2>
            <div class="info-row">
                <span class="info-label">{$t('pdf.credit.farmer')}</span>
                <span class="info-value">{$nomAgriculteur}</span>
            </div>
            <div class="info-row">
                <span class="info-label">{$t('pdf.credit.email')}</span>
                <span class="info-value">{$emailAgriculteur}</span>
            </div>
            <div class="info-row">
                <span class="info-label">{$t('pdf.credit.location')}</span>
                <span class="info-value">{$locParcelle} ({$surfParcelle} ha)</span>
            </div>
            <div class="info-row">
                <span class="info-label">{$t('pdf.credit.duration')}</span>
                <span class="info-value">{$duree} {$t('pdf.credit.years')}</span>
            </div>
        </div>

        <div class="section">
            <h2>{$t('pdf.credit.section2')}</h2>
            <div class="score-box">
                <div class="label">{$t('pdf.credit.global_risk')}</div>
                <div class="value">{$scoreRisque} / 10</div>
                <div class="label" style="font-weight: bold;">{$t('pdf.credit.level')} {$niveauRisqueStr}</div>
            </div>

            <table class="table">
                <thead>
                    <tr>
                        <th>{$t('pdf.credit.criteria')}</th>
                        <th style="text-align: center;">{$t('pdf.credit.score')}</th>
                        <th style="text-align: center;">{$t('pdf.credit.weight')}</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>{$t('pdf.credit.economic_profitability')}</td>
                        <td style="text-align: center;">{$scoreRentabilite}</td>
                        <td style="text-align: center;">40%</td>
                    </tr>
                    <tr>
                        <td>{$t('pdf.credit.climate_stability')}</td>
                        <td style="text-align: center;">{$scoreClimat}</td>
                        <td style="text-align: center;">30%</td>
                    </tr>
                    <tr>
                        <td>{$t('pdf.credit.diversification')}</td>
                        <td style="text-align: center;">{$scoreDiversification}</td>
                        <td style="text-align: center;">20%</td>
                    </tr>
                    <tr>
                        <td>{$t('pdf.credit.experience')}</td>
                        <td style="text-align: center;">{$scoreHistorique}</td>
                        <td style="text-align: center;">10%</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="section">
            <h2>{$t('pdf.credit.section3')}</h2>
            <div class="info-row">
                <span class="info-label">{$t('pdf.credit.repayment_capacity')}</span>
                <span class="info-value"><strong>{$capacite} {$t('pdf.credit.dt')}</strong></span>
            </div>
            <div class="info-row">
                <span class="info-label">{$t('pdf.credit.max_loan')}</span>
                <span class="info-value"><strong>{$montantMax} {$t('pdf.credit.dt')}</strong></span>
            </div>
        </div>

        <div class="section">
            <h2>{$t('pdf.credit.section4')}</h2>
            <div class="recommendations">
                {$recommandationsHtml}
            </div>
        </div>

        <div class="footer">
            <p>{$t('pdf.credit.recommendations')}</p>
            <p>{$t('pdf.credit.dossier_id')} #{$idDossier} | {$t('pdf.credit.creation_date')} {$dateCreation}</p>
        </div>
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

