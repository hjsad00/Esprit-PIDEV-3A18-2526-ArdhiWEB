<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Participation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

class ParticipationCertificatePdfGenerator
{
    public function __construct(
        private Environment $twig,
    ) {}

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

        $html = $this->twig->render('Evenement/certificate.html.twig', [
            'participantName' => $participantName,
            'eventTitle' => $eventTitle,
            'location' => $location,
            'dates' => $dates,
            'organizer' => $organizer,
            'issuedAt' => $issuedAt,
            'reference' => $reference,
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
