<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Participation;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EvenementParticipationMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private ParticipationCertificatePdfGenerator $certificatePdfGenerator,
        private QRCodeService $qrCodeService,  // NEW: Inject QR code service
        private UrlGeneratorInterface $urlGenerator,
        private string $mailFrom
    ) {}

    public function sendInscriptionConfirmation(Participation $participation): void
    {
        $event = $participation->getEvenement();
        $user = $participation->getUtilisateur();

        if (!$user?->getEmail() || !$event) {
            return;
        }

        // Generate QR code for email
        $qrCodeBase64 = $this->qrCodeService->genererQRCodeBase64($participation);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Ardhi Evenements'))
            ->to(new Address($user->getEmail(), trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''))))
            ->subject(sprintf('Inscription confirmee: %s', $event->getTitre()))
            ->htmlTemplate('Evenement/inscription_confirmation_email.html.twig')
            ->context([
                'participation' => $participation,
                'evenement' => $event,
                'qrCode' => $qrCodeBase64,  // NEW: Pass QR code
                'reviewUrl' => $this->urlGenerator->generate(
                    'app_evenement_show',
                    ['id' => $event->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ]);

        $this->mailer->send($email);
    }

    public function sendEventReminder(Participation $participation, int $daysBefore): void
    {
        $event = $participation->getEvenement();
        $user = $participation->getUtilisateur();

        if (!$user?->getEmail() || !$event) {
            return;
        }

        // Generate QR code for email
        $qrCodeBase64 = $this->qrCodeService->genererQRCodeBase64($participation);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Ardhi Evenements'))
            ->to(new Address($user->getEmail(), trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''))))
            ->subject(sprintf('Rappel J-%d: %s', $daysBefore, $event->getTitre()))
            ->htmlTemplate('Evenement/rappel_participation_email.html.twig')
            ->context([
                'participation' => $participation,
                'evenement' => $event,
                'qrCode' => $qrCodeBase64,  // NEW: Pass QR code
                'daysBefore' => $daysBefore,
                'eventUrl' => $this->urlGenerator->generate(
                    'app_evenement_show',
                    ['id' => $event->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ]);

        $this->mailer->send($email);
    }

    public function sendPresenceCertificateAndReviewInvite(Participation $participation): void
    {
        $event = $participation->getEvenement();
        $user = $participation->getUtilisateur();

        if (!$user?->getEmail() || !$event) {
            return;
        }

        $pdf = $this->certificatePdfGenerator->generate($participation);
        
        // Generate QR code for email
        $qrCodeBase64 = $this->qrCodeService->genererQRCodeBase64($participation);

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Ardhi Evenements'))
            ->to(new Address($user->getEmail(), trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''))))
            ->subject(sprintf('Votre attestation de presence - %s', $event->getTitre()))
            ->htmlTemplate('Evenement/attestation_presence_email.html.twig')
            ->context([
                'participation' => $participation,
                'evenement' => $event,
                'qrCode' => $qrCodeBase64,  // NEW: Pass QR code
                'reviewUrl' => $this->urlGenerator->generate(
                    'app_evenement_show',
                    ['id' => $event->getId()],
                    UrlGeneratorInterface::ABSOLUTE_URL
                ),
            ])
            ->attach($pdf, sprintf('attestation_presence_%d.pdf', $participation->getId()), 'application/pdf');

        $this->mailer->send($email);
    }
}
