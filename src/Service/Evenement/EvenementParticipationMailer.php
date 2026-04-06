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

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Ardhi Evenements'))
            ->to(new Address($user->getEmail(), trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''))))
            ->subject(sprintf('Inscription confirmee: %s', $event->getTitre()))
            ->htmlTemplate('Evenement/inscription_confirmation_email.html.twig')
            ->context([
                'participation' => $participation,
                'evenement' => $event,
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

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Ardhi Evenements'))
            ->to(new Address($user->getEmail(), trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''))))
            ->subject(sprintf('Rappel J-%d: %s', $daysBefore, $event->getTitre()))
            ->htmlTemplate('Evenement/rappel_participation_email.html.twig')
            ->context([
                'participation' => $participation,
                'evenement' => $event,
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

        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Ardhi Evenements'))
            ->to(new Address($user->getEmail(), trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? ''))))
            ->subject(sprintf('Votre attestation de presence - %s', $event->getTitre()))
            ->htmlTemplate('Evenement/attestation_presence_email.html.twig')
            ->context([
                'participation' => $participation,
                'evenement' => $event,
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
