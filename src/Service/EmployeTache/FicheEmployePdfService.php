<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Employe;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service dédié à la génération du PDF de la Fiche Employé
 */
class FicheEmployePdfService
{
    private QrCodeService $qrCodeService;
    private TranslatorInterface $translator;

    public function __construct(QrCodeService $qrCodeService, TranslatorInterface $translator)
    {
        $this->qrCodeService = $qrCodeService;
        $this->translator = $translator;
    }

    /**
     * Génère la fiche de l'employé et retourne le contenu brut du PDF.
     * Cette méthode reproduit fidèlement la mise en page "creerFichePDF" du desktop.
     */
    public function genererFichePdf(Employe $employe, string $appPublicDir): string
    {
        // Création du document (A4, Portrait)
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Métadonnées
        $pdf->SetCreator('Ardhi - Ressources Humaines');
        $pdf->SetAuthor('Ardhi');
        $pdf->SetTitle($this->translator->trans('employe.fiche_title') . ' - ' . $employe->getNomComplet());

        // Marges et configuration d'impression
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false); // En-tête personnalisé
        $pdf->setPrintFooter(false); // Pied de page personnalisé

        $currentLocale = $this->translator->getLocale();
        $isAr = ($currentLocale === 'ar');
        $font = $isAr ? 'freeserif' : 'helvetica';

        if ($isAr) {
            $pdf->setRTL(true);
        }

        $pdf->AddPage();

        // ── Couleurs de base ───────────────────────────────────────────────
        $vertArdhiR = 39; $vertArdhiG = 174; $vertArdhiB = 96; // #27ae60
        $vertFonceR = 27; $vertFonceG = 94; $vertFonceB = 32;  // #1B5E20
        $bleuAccentR = 41; $bleuAccentG = 128; $bleuAccentB = 185; // #2980b9
        $grisClairR = 245; $grisClairG = 247; $grisClairB = 250; // #F5F7FA
        
        // ── EN-TETE : Logo Ardhi + Titre + Date + ID ────────────────────────
        $pdf->SetFillColor($vertArdhiR, $vertArdhiG, $vertArdhiB);
        $pdf->Rect(10, 10, 140, 30, 'F'); // Bloc gauche
        
        $pdf->SetFillColor($vertFonceR, $vertFonceG, $vertFonceB);
        $pdf->Rect(150, 10, 50, 30, 'F'); // Bloc droit

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 26);
        $pdf->SetXY(20, 12);
        $pdf->Cell(130, 12, 'ARDHI', 0, 1, 'L');
        
        $pdf->SetTextColor(200, 255, 200);
        $pdf->SetFont($font, 'B', 14);
        $pdf->SetXY(20, 24);
        $pdf->Cell(130, 10, $this->translator->trans('employe.fiche_title'), 0, 1, 'L');

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 16);
        $pdf->SetXY(150, 12);
        $pdf->Cell(50, 12, $this->translator->trans('common.id') . ' : #' . $employe->getId(), 0, 1, 'C');

        $pdf->SetTextColor(180, 220, 180);
        $pdf->SetFont($font, '', 10);
        $pdf->SetXY(150, 24);
        $pdf->Cell(50, 10, date('d/m/Y'), 0, 1, 'C');

        $pdf->SetY(45);

        // ── CORPS : Photo + QR Code ────────────────────────────────────────
        $yPhotoQr = $pdf->GetY();
        
        // Bloc Photo (gauche)
        $pdf->SetLineStyle(array('width' => 0.5, 'color' => array($vertArdhiR, $vertArdhiG, $vertArdhiB)));
        $pdf->SetFillColor($grisClairR, $grisClairG, $grisClairB);
        $pdf->Rect(10, $yPhotoQr, 90, 60, 'DF'); // Draw & Fill

        if ($employe->hasPhoto() && file_exists($appPublicDir . '/' . $employe->getPhotoPath())) {
            // Image centrée (approx)
            $pdf->Image($appPublicDir . '/' . $employe->getPhotoPath(), 30, $yPhotoQr + 5, 50, 50, '', '', '', false, 300, '', false, false, 0, 'C');
        } else {
            // Avatar texte
            $pdf->SetTextColor($vertArdhiR, $vertArdhiG, $vertArdhiB);
            $pdf->SetFont($font, 'B', 46);
            $pdf->SetXY(10, $yPhotoQr + 10);
            $pdf->Cell(90, 30, $employe->getInitiales(), 0, 1, 'C', true);
        }
        
        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont($font, 'B', 10);
        $pdf->SetXY(10, $yPhotoQr + 55);
        $pdf->Cell(90, 5, strtoupper($this->translator->trans('employe.form.photo')), 0, 1, 'C');

        // Bloc QR Code (droite)
        $pdf->Rect(110, $yPhotoQr, 90, 60, 'DF');
        try {
            $qrUrl = $this->qrCodeService->generateFicheUrl((int) $employe->getId());
            $qrSvg = $this->qrCodeService->generateQrCodeSvg($qrUrl, 200);
            
            // Image magique depuis string en TCPDF SVG
            $pdf->ImageSVG('@' . $qrSvg, 130, $yPhotoQr + 5, 50, 50, '', '', '', 0, false);
        } catch (\Exception $e) {
            $pdf->SetXY(110, $yPhotoQr + 20);
            $pdf->Cell(90, 20, 'QR Code Indisponible', 0, 1, 'C');
        }

        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetFont($font, 'I', 8);
        $pdf->SetXY(110, $yPhotoQr + 55);
        $pdf->Cell(90, 5, $this->translator->trans('employe.scan_mobile'), 0, 1, 'C');

        $pdf->SetY($yPhotoQr + 65);

        // ── SECTION IDENTITE ───────────────────────────────────────────────
        $this->addSectionTitle($pdf, $this->translator->trans('employe.form.identity'), $vertArdhiR, $vertArdhiG, $vertArdhiB, $font);
        
        $pNom = $employe->getNom();
        $pPrenom = $employe->getPrenom();
        $pPoste = $employe->getPoste() ?? '—';
        $pStatut = $employe->isActif() ? "✓ " . $this->translator->trans('common.active') : "✗ " . $this->translator->trans('common.inactive');

        $this->addRow($pdf, $this->translator->trans('employe.col.nom'), $pNom ?? '', $this->translator->trans('employe.col.prenom'), $pPrenom ?? '', true, $font);
        $this->addRow($pdf, $this->translator->trans('employe.col.poste'), $pPoste, $this->translator->trans('employe.col.actif'), $pStatut, false, $font);

        $pdf->SetY($pdf->GetY() + 5);

        // ── SECTION CONTACT ────────────────────────────────────────────────
        $this->addSectionTitle($pdf, $this->translator->trans('employe.form.contact'), $bleuAccentR, $bleuAccentG, $bleuAccentB, $font);
        
        $pEmail = $employe->getEmail() ?? '—';
        $pTel = $employe->getTelephone() ?? '—';

        $this->addRow($pdf, $this->translator->trans('employe.col.email'), $pEmail, $this->translator->trans('employe.col.telephone'), $pTel, true, $font);

        $pdf->SetY($pdf->GetY() + 10);

        // ── BADGE STATUT GRAND FORMAT ──────────────────────────────────────
        $pdf->SetX(60);
        if ($employe->isActif()) {
            $pdf->SetFillColor($vertArdhiR, $vertArdhiG, $vertArdhiB);
            $vText = "  ✓  " . $this->translator->trans('common.active') . "  ";
        } else {
            $pdf->SetFillColor(192, 57, 43); // Rouge
            $vText = "  ✗  " . $this->translator->trans('common.inactive') . "  ";
        }
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 10);
        $pdf->Cell(90, 10, $vText, 0, 1, 'C', true);

        // ── PIED DE PAGE ───────────────────────────────────────────────────
        $pdf->SetY(270);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->SetFont($font, 'I', 8);
        $footer = $this->translator->trans('common.generated_by', ['%name%' => 'Ardhi', '%date%' => date('d/m/Y à H:i')]);
        $pdf->Cell(0, 5, $footer . ' · ' . ($isAr ? 'سري للغاية' : 'Confidentiel'), 0, 1, 'C');

        return $pdf->Output('fiche.pdf', 'S');
    }

    private function addSectionTitle(\TCPDF $pdf, string $title, int $r, int $g, int $b, string $font): void
    {
        $pdf->SetFillColor(27, 94, 32); // Vert foncé
        if ($r != 39) $pdf->SetFillColor(13, 71, 161); // Si bleu, on passe en bleu foncé

        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetFont($font, 'B', 11);
        $pdf->Cell(190, 8, '  ' . $title, 0, 1, 'L', true);
    }

    private function addRow(\TCPDF $pdf, string $l1, string $v1, string $l2, string $v2, bool $gris, string $font): void
    {
        if ($gris) {
            $pdf->SetFillColor(245, 247, 250); // Gris clair
        } else {
            $pdf->SetFillColor(255, 255, 255);
        }

        $pdf->SetLineStyle(array('width' => 0.1, 'color' => array(220, 220, 220)));

        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont($font, 'B', 10);
        $pdf->Cell(35, 8, '  ' . $l1, 1, 0, 'L', true);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont($font, '', 10);
        $pdf->Cell(60, 8, ' ' . $v1, 1, 0, 'L', true);

        $pdf->SetTextColor(80, 80, 80);
        $pdf->SetFont($font, 'B', 10);
        $pdf->Cell(35, 8, '  ' . $l2, 1, 0, 'L', true);

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont($font, '', 10);
        $pdf->Cell(60, 8, ' ' . $v2, 1, 1, 'L', true);
    }
}
