<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Participation;
use Knp\Snappy\Pdf;
use Twig\Environment;

class ParticipationCertificatePdfGenerator
{
    public function __construct(
        private Pdf $snappy,
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

        return $this->snappy->getOutputFromHtml($html, [
            'page-size' => 'A4',
            'orientation' => 'Portrait',
            'encoding' => 'UTF-8',
            'no-outline' => true,
            'margin-top' => '0',
            'margin-right' => '0',
            'margin-bottom' => '0',
            'margin-left' => '0',
        ]);
    }
}
