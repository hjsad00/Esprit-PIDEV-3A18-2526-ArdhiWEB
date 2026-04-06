<?php

namespace App\Service;

use App\Entity\Evenement\Participation;

class ParticipationCertificatePdfGenerator
{
    public function generate(Participation $participation): string
    {
        $event = $participation->getEvenement();
        $user = $participation->getUtilisateur();

        $participantName = trim(sprintf('%s %s', $user?->getPrenom() ?? '', $user?->getNom() ?? '')) ?: 'Participant';
        $eventTitle = $event?->getTitre() ?? '-';
        $location = $event?->getLieu() ?? '-';
        $dates = sprintf(
            'Du %s au %s',
            $event?->getDateDebut()?->format('d/m/Y') ?? '-',
            $event?->getDateFin()?->format('d/m/Y') ?? '-'
        );
        $organizer = $event?->getOrganisateur() ?? '-';
        $issuedAt = (new \DateTimeImmutable())->format('d/m/Y H:i');
        $reference = sprintf('EVT-%s-P%s', $event?->getId() ?? 'X', $participation->getId() ?? 'X');

        return $this->buildStyledPdf([
            'participantName' => $participantName,
            'eventTitle' => $eventTitle,
            'location' => $location,
            'dates' => $dates,
            'organizer' => $organizer,
            'issuedAt' => $issuedAt,
            'reference' => $reference,
        ]);
    }

    private function buildStyledPdf(array $data): string
    {
        $stream = [];
        $stream[] = '0.97 0.98 0.96 rg';
        $stream[] = '0 0 595 842 re f';
        $stream[] = '0.12 0.44 0.33 rg';
        $stream[] = '28 28 539 786 re f';
        $stream[] = '0.99 0.99 0.98 rg';
        $stream[] = '42 42 511 758 re f';
        $stream[] = '0.88 0.93 0.87 rg';
        $stream[] = '58 650 479 100 re f';
        $stream[] = '0.95 0.90 0.79 rg';
        $stream[] = '58 170 479 112 re f';

        $stream[] = 'BT';
        $stream[] = '/F2 14 Tf';
        $stream[] = '0.28 0.44 0.35 rg';
        $stream[] = '235 785 Td';
        $stream[] = '(' . $this->escapePdfText('ARDHI') . ') Tj';
        $stream[] = 'ET';

        $stream[] = $this->textBlock(105, 710, '/F2', 26, [0.11, 0.29, 0.22], [
            'ATTESTATION DE PRESENCE',
        ]);

        $stream[] = $this->textBlock(105, 676, '/F1', 12, [0.34, 0.45, 0.39], [
            'Document officiel de participation aux evenements Ardhi',
        ]);

        $stream[] = $this->textBlock(105, 610, '/F1', 13, [0.30, 0.38, 0.34], [
            'Cette attestation certifie que',
        ]);

        $stream[] = $this->textBlock(105, 575, '/F2', 24, [0.12, 0.24, 0.20], [
            strtoupper($data['participantName']),
        ]);

        $stream[] = $this->textBlock(105, 535, '/F1', 14, [0.21, 0.32, 0.27], [
            'a effectivement participe a l evenement suivant :',
        ]);

        $stream[] = $this->textBlock(105, 495, '/F2', 20, [0.13, 0.28, 0.44], [
            $data['eventTitle'],
        ]);

        $stream[] = $this->textBlock(105, 448, '/F1', 13, [0.25, 0.32, 0.29], [
            'Lieu : ' . $data['location'],
            'Dates : ' . $data['dates'],
            'Organisateur : ' . $data['organizer'],
        ], 20);

        $stream[] = $this->textBlock(105, 245, '/F2', 15, [0.45, 0.33, 0.13], [
            'Reference : ' . $data['reference'],
        ]);

        $stream[] = $this->textBlock(105, 218, '/F1', 12, [0.42, 0.36, 0.23], [
            'Date d emission : ' . $data['issuedAt'],
        ]);

        $stream[] = $this->textBlock(355, 218, '/F1', 12, [0.42, 0.36, 0.23], [
            'Signature : Ardhi Evenements',
        ]);

        $stream[] = $this->textBlock(105, 130, '/F1', 11, [0.34, 0.45, 0.39], [
            'Merci pour votre engagement et votre presence.',
            'Cette attestation a ete generee automatiquement par la plateforme Ardhi.',
        ], 16);

        $objects = [];
        $objects[] = "1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj";
        $objects[] = "2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj";
        $objects[] = "3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /Contents 6 0 R >> endobj";
        $objects[] = "4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj";
        $objects[] = "5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold >> endobj";
        $objects[] = sprintf(
            "6 0 obj << /Length %d >> stream\n%s\nendstream endobj",
            strlen(implode("\n", $stream)),
            implode("\n", $stream)
        );

        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object . "\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n";
        $pdf .= "0000000000 65535 f \n";
        for ($i = 1; $i <= count($objects); $i++) {
            $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        }
        $pdf .= "trailer << /Size " . (count($objects) + 1) . " /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }

    private function escapePdfText(string $text): string
    {
        return str_replace(
            ['\\', '(', ')', "\r", "\n"],
            ['\\\\', '\(', '\)', '', ' '],
            $text
        );
    }

    private function textBlock(int $x, int $y, string $font, int $size, array $rgb, array $lines, int $leading = 18): string
    {
        $parts = [];
        $parts[] = 'BT';
        $parts[] = sprintf('%s %d Tf', $font, $size);
        $parts[] = sprintf('%.2F %.2F %.2F rg', $rgb[0], $rgb[1], $rgb[2]);
        $parts[] = sprintf('%d %d Td', $x, $y);
        $parts[] = sprintf('%d TL', $leading);

        $first = true;
        foreach ($lines as $line) {
            if (!$first) {
                $parts[] = 'T*';
            }
            $first = false;
            $parts[] = '(' . $this->escapePdfText($line) . ') Tj';
        }

        $parts[] = 'ET';

        return implode("\n", $parts);
    }
}
