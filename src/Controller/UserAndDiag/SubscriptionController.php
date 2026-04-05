<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\Abonnement;
use App\Entity\UserAndDiag\Offre;
use App\Repository\UserAndDiag\AbonnementRepository;
use App\Repository\UserAndDiag\OffreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/subscription')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class SubscriptionController extends AbstractController
{
    #[Route('', name: 'app_user_and_diag_subscription', methods: ['GET'])]
    public function index(OffreRepository $offreRepo, AbonnementRepository $aboRepo): Response
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        $offres = $offreRepo->findActiveOffers();
        $activeAbo = $aboRepo->findActiveByUser($user);

        return $this->render('UserAndDiag/subscription/index.html.twig', [
            'offres' => $offres,
            'activeAbo' => $activeAbo,
        ]);
    }

    #[Route('/checkout/{id}', name: 'app_user_and_diag_subscribe_checkout', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function checkout(Offre $offre, Request $request, \App\Service\UserAndDiag\StripeService $stripeService): JsonResponse
    {
        $duration = (int) $request->request->get('duration', 1);
        if (!in_array($duration, [1, 3, 6, 12])) {
            return $this->json(['error' => 'Durée invalide.'], 400);
        }

        // Calculate total amount. E.g., 29.99 * 3 months.
        // Cents calculation: price * duration * 100
        $totalCents = (int) round($offre->getPrixMensuel() * $duration * 100);
        $productName = "Abonnement " . $offre->getNom() . " ($duration mois)";

        $metadata = [
            'offre_id' => $offre->getId(),
            'duration' => $duration
        ];

        $checkoutResult = $stripeService->createCheckoutSession($productName, $totalCents, 'eur', $metadata);

        if ($checkoutResult['success']) {
            return $this->json(['checkoutUrl' => $checkoutResult['checkoutUrl']]);
        }

        return $this->json(['error' => 'Erreur Stripe: ' . ($checkoutResult['error'] ?? 'Inconnue')], 500);
    }

    #[Route('/payment-success', name: 'app_user_and_diag_subscription_payment_success', methods: ['GET'])]
    public function paymentSuccess(
        Request $request,
        \App\Service\UserAndDiag\StripeService $stripeService,
        EntityManagerInterface $em,
        OffreRepository $offreRepo,
        AbonnementRepository $aboRepo
    ): Response {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $sessionId = $request->query->get('session_id');

        if (!$sessionId) {
            $this->addFlash('danger', 'Session Stripe invalide.');
            return $this->redirectToRoute('app_user_and_diag_subscription');
        }

        $sessionData = $stripeService->checkSessionStatus($sessionId);

        // Ensure session was successful and paid
        if (!$sessionData || $sessionData['payment_status'] !== 'paid') {
            $this->addFlash('danger', 'Le paiement n\'a pas été validé.');
            return $this->redirectToRoute('app_user_and_diag_subscription');
        }

        $offreId = $sessionData['metadata']['offre_id'] ?? null;
        $duration = $sessionData['metadata']['duration'] ?? null;

        if (!$offreId || !$duration) {
            $this->addFlash('danger', 'Données de paiement incomplètes.');
            return $this->redirectToRoute('app_user_and_diag_subscription');
        }

        $offre = $offreRepo->find($offreId);
        if (!$offre) {
            $this->addFlash('danger', 'Offre introuvable.');
            return $this->redirectToRoute('app_user_and_diag_subscription');
        }

        // Cancel existing
        $existing = $aboRepo->findActiveByUser($user);
        if ($existing) {
            $existing->setStatut('ANNULE');
        }

        $abo = new Abonnement();
        $abo->setUser($user);
        $abo->setOffre($offre);
        $abo->setType($offre->getNom());

        // Save the total price paid
        $totalPaid = $sessionData['amount_total'] / 100.0;
        $abo->setPrix($totalPaid);

        $abo->setDateDebut(new \DateTime());
        $abo->setDateFin((new \DateTime())->modify("+$duration month"));
        $abo->setStatut('ACTIF');

        $em->persist($abo);
        $em->flush();

        $this->addFlash('success', 'Paiement réussi ! Votre abonnement est activé.');
        return $this->redirectToRoute('app_user_and_diag_subscription');
    }

    #[Route('/payment-cancelled', name: 'app_user_and_diag_subscription_payment_cancelled', methods: ['GET'])]
    public function paymentCancelled(): Response
    {
        $this->addFlash('warning', 'Le paiement a été annulé.');
        return $this->redirectToRoute('app_user_and_diag_subscription');
    }

    #[Route('/cancel', name: 'app_user_and_diag_subscription_cancel', methods: ['POST'])]
    public function cancel(EntityManagerInterface $em, AbonnementRepository $aboRepo): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $abo = $aboRepo->findActiveByUser($user);

        if (!$abo) {
            return $this->json(['error' => 'Aucun abonnement actif.'], 404);
        }

        $abo->setStatut('ANNULE');
        $em->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/invoice', name: 'app_user_and_diag_subscription_invoice', methods: ['GET'])]
    public function invoice(AbonnementRepository $aboRepo): Response
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $abo = $aboRepo->findActiveByUser($user);

        if (!$abo) {
            $this->addFlash('danger', 'Aucun abonnement actif trouvé.');
            return $this->redirectToRoute('app_user_and_diag_subscription');
        }

        $html = $this->renderInvoiceHtml($user, $abo);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Facture_' . str_replace(' ', '_', $abo->getType()) . '_' . date('Ymd') . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function renderInvoiceHtml($user, Abonnement $abo): string
    {
        $dateDebut = $abo->getDateDebut() ? $abo->getDateDebut()->format('d/m/Y') : '-';
        $dateFin = $abo->getDateFin() ? $abo->getDateFin()->format('d/m/Y') : '-';
        $today = date('d/m/Y');
        $nom = strtoupper($user->getNom()) . ' ' . $user->getPrenom();
        $email = $user->getEmail();
        $type = $abo->getType();
        $prix = number_format($abo->getPrix(), 2, ',', ' ');

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8">
<style>
    body { font-family: Helvetica, Arial, sans-serif; color: #333; margin: 40px; }
    .header { text-align: center; margin-bottom: 30px; }
    .header h1 { color: #4A5A2B; font-size: 28px; margin-bottom: 5px; }
    .header .subtitle { color: #999; font-size: 12px; }
    .company, .client { margin-bottom: 20px; }
    .company { color: #555; font-size: 13px; line-height: 1.8; }
    .divider { border-top: 2px solid #e0e0e0; margin: 20px 0; }
    .client-label { font-weight: bold; font-size: 14px; margin-bottom: 6px; }
    .client-info { font-size: 13px; line-height: 1.7; }
    table { width: 100%; border-collapse: collapse; margin: 25px 0; }
    th { background: #4A5A2B; color: white; padding: 12px 15px; text-align: left; font-size: 13px; }
    td { padding: 12px 15px; border-bottom: 1px solid #eee; font-size: 13px; }
    .total { text-align: right; font-size: 20px; font-weight: bold; color: #c0392b; margin-top: 20px; }
    .footer { text-align: center; color: #999; font-style: italic; margin-top: 50px; font-size: 12px; }
</style>
</head>
<body>
    <div class="header">
        <h1>🌿 Facture ARDHI</h1>
        <div class="subtitle">Document généré automatiquement</div>
    </div>

    <div class="company">
        <strong>Ardhi Inc.</strong><br>
        123 Avenue de l'Agriculture<br>
        Tunis, Tunisie<br>
        Email: contact@ardhi.tn
    </div>

    <div class="divider"></div>

    <div class="client">
        <div class="client-label">Facturé à :</div>
        <div class="client-info">
            Nom : {$nom}<br>
            Email : {$email}<br>
            Date : {$today}
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th>Description</th>
                <th>Durée</th>
                <th>Montant</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>{$type}</td>
                <td>Du {$dateDebut} au {$dateFin}</td>
                <td>{$prix} DT</td>
            </tr>
        </tbody>
    </table>

    <div class="total">Total Payé : {$prix} DT</div>

    <div class="footer">Merci de votre confiance ! — Ardhi Inc.</div>
</body>
</html>
HTML;
    }
}
