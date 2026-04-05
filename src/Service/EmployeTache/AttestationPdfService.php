<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Employe;

/**
 * Service dédié à la génération du PDF d'Attestation de Travail
 */
class AttestationPdfService
{
    /**
     * Génère l'attestation de travail et retourne le contenu brut du PDF.
     */
    public function genererAttestationPdf(Employe $employe): string
    {
        // Création du document (A4, Portrait)
        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);

        // Métadonnées
        $pdf->SetCreator('Ardhi - Ressources Humaines');
        $pdf->SetAuthor('Ardhi');
        $pdf->SetTitle('Attestation de Travail - ' . $employe->getNomComplet());

        // Marges et configuration d'impression
        $pdf->SetMargins(20, 20, 20);
        $pdf->SetAutoPageBreak(true, 20);
        $pdf->setPrintHeader(false); // Pas d'en-tête par défaut
        $pdf->setPrintFooter(false); // Pied de page géré manuellement

        $pdf->AddPage();

        // --- EN-TÊTE BLEU MARINE ---
        // Couleur de fond bleu marine : #1A3A54 (RGB: 26, 58, 84)
        $pdf->SetFillColor(26, 58, 84);
        // Rectangle de l'en-tête, qui prend toute la largeur
        $pdf->Rect(0, 0, 210, 50, 'F');

        // Textes de l'en-tête
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetY(15);
        
        // On ajouterait le logo Ardhi ici si on a le bon chemin
        // Exemple: $pdf->Image($logoPath, 20, 15, 20);

        $pdf->SetFont('helvetica', 'B', 22);
        // On décale le texte vers la droite pour laisser la place au logo
        $pdf->Cell(0, 10, 'Ardhi – Ressources Humaines', 0, 1, 'C');
        
        $pdf->SetFont('helvetica', '', 12);
        $pdf->SetTextColor(180, 200, 220); // Gris-bleu clair
        $pdf->Cell(0, 10, 'Gestion Agricole Intelligente', 0, 1, 'C');

        // --- CORPS DE L'ATTESTATION ---
        $pdf->SetY(80);
        $pdf->SetTextColor(40, 40, 40); // Gris très foncé
        $pdf->SetFont('helvetica', '', 12);

        $dateJour = (new \DateTime())->format('d/m/Y');
        
        $html = "
            <p>Madame, Monsieur <strong>" . htmlspecialchars($employe->getNomComplet()) . "</strong>,</p>
            <br>
            <p>Nous avons le plaisir de vous adresser votre <strong>attestation de travail</strong> officielle, 
            établie à la date du <strong>" . $dateJour . "</strong>.</p>
            <br>
            <p>Ce document certifie que vous occupez le poste de <strong>" . htmlspecialchars((string)$employe->getPoste()) . "</strong> 
            au sein de notre structure agricole via la plateforme <strong>Ardhi</strong>, 
            et que vous y exercez vos fonctions de manière régulière et continue.</p>
        ";

        $pdf->writeHTML($html, true, false, true, false, '');

        // --- SIGNATURE ---
        $pdf->SetY($pdf->GetY() + 30);
        $pdf->SetFont('helvetica', '', 12);
        $pdf->Cell(0, 8, 'Cordialement,', 0, 1, 'L');
        $pdf->SetFont('helvetica', 'B', 12);
        $pdf->Cell(0, 8, 'La Direction des Ressources Humaines', 0, 1, 'L');
        $pdf->SetFont('helvetica', '', 11);
        $pdf->Cell(0, 8, 'Ardhi – Gestion Agricole', 0, 1, 'L');

        // --- PIED DE PAGE ---
        $pdf->SetY(270);
        $pdf->SetFont('helvetica', 'I', 9);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->Cell(0, 0, 'Document officiel · ' . $dateJour . ' · Ardhi © ' . date('Y') . ' · Toute reproduction non autorisée est interdite', 0, 0, 'C');

        // Retourner le PDF sous forme de chaîne de caractères brute (pour attachement email)
        return $pdf->Output('attestation.pdf', 'S');
    }
}
