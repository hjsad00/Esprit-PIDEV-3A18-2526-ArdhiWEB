<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Employe;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;

/**
 * Service dédié à l'envoi de l'attestation par email avec Symfony Mailer
 */
class AttestationMail
{
    public function __construct(
        private MailerInterface $mailer,
        private AttestationPdfService $pdfService
    ) {}

    /**
     * Envoie l'attestation de travail à l'employé
     * @return bool True si envoyé avec succès
     */
    public function envoyerAttestation(Employe $employe): bool
    {
        if (!$employe->getEmail()) {
            throw new \Exception("Cet employé n'a pas d'adresse email valide.");
        }

        try {
            // 1. Générer le contenu du PDF en mémoire
            $pdfContent = $this->pdfService->genererAttestationPdf($employe);

            // 2. Préparer l'email avec le template Twig
            $email = (new TemplatedEmail())
                ->from(new Address('rh@ardhi.com', 'Ardhi - Ressources Humaines'))
                ->to(new Address($employe->getEmail(), $employe->getNomComplet()))
                ->subject('Votre Attestation de Travail - Ardhi')
                ->htmlTemplate('EmployeTache/email/attestation.html.twig')
                ->context([
                    'employe' => $employe,
                    'dateJour' => (new \DateTime())->format('d/m/Y')
                ]);

            // 3. Attacher le PDF (DataPart depuis chaîne binaire en mémoire)
            $nomFichier = 'attestation_' . strtolower(str_replace(' ', '_', $employe->getNomComplet())) . '.pdf';
            $email->addPart(new DataPart($pdfContent, $nomFichier, 'application/pdf'));

            // 4. Envoyer l'email
            $this->mailer->send($email);

            return true;

        } catch (\Exception $e) {
            // En cas d'erreur de mailer, on peut logger l'erreur
            throw new \Exception("Erreur lors de l'envoi de l'email : " . $e->getMessage());
        }
    }
}
