<?php

namespace App\Service\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Maintenance;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class MaintenanceMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private string $mailFrom
    ) {}

    public function sendUrgentAcceptedEmail(string $toEmail, string $toName): void
    {
        $this->sendEmail(
            $toEmail,
            $toName,
            "Urgent : Maintenance de votre matériel approuvée",
            'emails/maintenance_urgent_accepted.html.twig',
            []
        );
    }

    public function sendPlanificationRequestedEmail(string $toEmail, string $toName): void
    {
        $this->sendEmail(
            $toEmail,
            $toName,
            "Action requise : Planification de maintenance",
            'emails/maintenance_planification_requested.html.twig',
            []
        );
    }

    private function sendEmail(string $toEmail, string $toName, string $subject, string $template, array $context): void
    {
        $email = (new TemplatedEmail())
            ->from(new Address($this->mailFrom, 'Ardhi Maintenance'))
            ->to(new Address($toEmail, $toName))
            ->subject($subject)
            ->htmlTemplate($template)
            ->context($context);

        $this->mailer->send($email);
    }
}
