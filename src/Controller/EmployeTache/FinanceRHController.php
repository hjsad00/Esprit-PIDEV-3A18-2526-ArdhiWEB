<?php

namespace App\Controller\EmployeTache;

use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;
use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\FinanceRHService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/finance-rh', name: 'finance_rh_')]
class FinanceRHController extends AbstractController
{
    public function __construct(
        private AgriculteurContextService $ctx,
        private FinanceRHService          $financeService,
        private EmployeRepository         $employeRepo,
        private TacheRepository           $tacheRepo,
        private EntityManagerInterface    $em,
    ) {}

    private function checkAccess(): int|Response
    {
        if (!$this->ctx->hasAccess()) {
            $this->addFlash('warning', '⛔ Accès réservé.');
            return $this->redirectToRoute('app_home');
        }
        $id = $this->ctx->getActiveAgriculteurId();
        if ($id === null) {
            $this->addFlash('info', 'Sélectionnez un agriculteur.');
            return $this->redirectToRoute('admin_agriculteurs_employe');
        }
        return $id;
    }

    // ── Dashboard ─────────────────────────────────────────────────────
    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;

        $data = $this->financeService->getDashboardFinancier($result);
        return $this->render('EmployeTache/finance/dashboard.html.twig', array_merge($data, [
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]));
    }

    // ── Bulletin JSON (pour le modal) ────────────────────────────────
    #[Route('/bulletin/{id}', name: 'bulletin', methods: ['GET'])]
    public function bulletin(int $id, Request $request): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $mois  = max(1, min(12,   (int) $request->query->get('mois',  date('n'))));
        $annee = max(2020, min(2030, (int) $request->query->get('annee', date('Y'))));

        $bulletin = $this->financeService->genererBulletinPaie($id, $mois, $annee);
        if (empty($bulletin)) {
            return new JsonResponse(['error' => 'Employé introuvable.'], 404);
        }

        $emp = $bulletin['employe'];
        return new JsonResponse([
            'employe'           => [
                'id'     => $emp->getId(),
                'nom'    => $emp->getNom(),
                'prenom' => $emp->getPrenom(),
                'poste'  => $emp->getPoste(),
                'email'  => $emp->getEmail(),
                'photo'  => $emp->getPhotoPath(),
            ],
            'mois'              => $bulletin['mois'],
            'annee'             => $bulletin['annee'],
            'nbJoursTravailles' => $bulletin['nbJoursTravailles'],
            'salaireJournalier' => $bulletin['salaireJournalier'],
            'salaireBrut'       => $bulletin['salaireBrut'],
            'cnssEmploye'       => $bulletin['cnssEmploye'],
            'cnssEmployeur'     => $bulletin['cnssEmployeur'],
            'irpp'              => $bulletin['irpp'],
            'salaireNet'        => $bulletin['salaireNet'],
            'coutTotalEmpl'     => $bulletin['coutTotalEmpl'],
            'tauxCNSSEmp'       => $bulletin['tauxCNSSEmp'],
            'tauxCNSSEmpr'      => $bulletin['tauxCNSSEmpr'],
            'typeContrat'       => $bulletin['typeContrat'],
        ]);
    }

    // ── ✅ NOUVEAU — Mise à jour salaire inline ──────────────────────
    #[Route('/salaire/{id}', name: 'update_salaire', methods: ['POST'])]
    public function updateSalaire(int $id, Request $request): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['success' => false, 'error' => 'Accès refusé'], 403);
        }
        $idAgriculteur = $result;

        $employe = $this->employeRepo->find($id);
        if (!$employe || $employe->getIdAgriculteur() !== $idAgriculteur) {
            return new JsonResponse(['success' => false, 'error' => 'Employé introuvable.'], 404);
        }

        $data    = json_decode($request->getContent(), true) ?? [];
        $salaire = (float) ($data['salaire'] ?? 0);

        if ($salaire <= 0 || $salaire > 5000) {
            return new JsonResponse([
                'success' => false,
                'error'   => 'Salaire invalide (1 – 5 000 TND/j).',
            ], 422);
        }

        // Mise à jour via le setter
        $employe->setSalaireJournalier($salaire);
        $this->em->flush();

        // Recalcul immédiat pour retourner les nouvelles valeurs
        $bulletin = $this->financeService->genererBulletinPaie($id, (int) date('n'), (int) date('Y'));

        return new JsonResponse([
            'success'       => true,
            'salaireJ'      => round($salaire, 3),
            'salaireBrut'   => $bulletin['salaireBrut']   ?? 0,
            'salaireNet'    => $bulletin['salaireNet']    ?? 0,
            'coutTotal'     => $bulletin['coutTotalEmpl'] ?? 0,
            'cnssEmployeur' => $bulletin['cnssEmployeur'] ?? 0,
        ]);
    }

    // ── ✅ NOUVEAU — Export PDF bulletin ─────────────────────────────
    #[Route('/bulletin/{id}/pdf', name: 'bulletin_pdf', methods: ['GET'])]
    public function bulletinPdf(int $id, Request $request): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $employe = $this->employeRepo->find($id);
        if (!$employe || $employe->getIdAgriculteur() !== $idAgriculteur) {
            throw $this->createNotFoundException('Employé introuvable.');
        }

        $mois  = max(1, min(12,   (int) $request->query->get('mois',  date('n'))));
        $annee = max(2020, min(2030, (int) $request->query->get('annee', date('Y'))));

        $b = $this->financeService->genererBulletinPaie($id, $mois, $annee);
        if (empty($b)) throw $this->createNotFoundException('Bulletin introuvable.');

        $pdf = new \TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Ardhi');
        $pdf->SetTitle("Bulletin {$employe->getNomComplet()} {$b['mois']} {$annee}");
        $pdf->SetMargins(18, 18, 18);
        $pdf->SetAutoPageBreak(true, 15);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        // ── Bandeau vert header
        $pdf->SetFillColor(33, 77, 46);   $pdf->Rect(0, 0, 210, 30, 'F');
        $pdf->SetFillColor(46, 99, 64);   $pdf->Rect(0, 27, 210, 4, 'F');
        $pdf->SetFont('dejavusans', 'B', 18);
        $pdf->SetTextColor(244, 250, 245);
        $pdf->SetY(5); $pdf->Cell(0, 10, 'BULLETIN DE PAIE', 0, 1, 'C');
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(163, 201, 175);
        $pdf->Cell(0, 5, "ARDHI Finance RH  ·  CNSS Tunisie 2024", 0, 1, 'C');

        $pdf->Ln(10);
        $pdf->SetTextColor(30, 30, 30);

        // ── Bloc identité employé
        $yStart = $pdf->GetY();
        $pdf->SetFillColor(240, 249, 242);
        $pdf->RoundedRect(18, $yStart, 174, 30, 4, '1111', 'F');
        $pdf->SetFont('dejavusans', 'B', 12);
        $pdf->SetTextColor(33, 77, 46);
        $pdf->SetXY(23, $yStart + 4);
        $pdf->Cell(0, 7, $employe->getNomComplet(), 0, 1);
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(94, 130, 104);
        $pdf->SetX(23);
        $pdf->Cell(87, 5, 'Poste : ' . ($employe->getPoste() ?? '—'), 0, 0);
        $pdf->Cell(87, 5, 'Email : ' . $employe->getEmail(), 0, 1);
        $pdf->SetX(23);
        $pdf->Cell(87, 5, 'Contrat : ' . $b['typeContrat'], 0, 0);
        $pdf->Cell(87, 5, "Période : {$b['mois']} {$annee}  ({$b['nbJoursTravailles']} jours)", 0, 1);
        $pdf->Ln(10);

        // ── Tableau lignes
        $this->pdfLig($pdf, 'Salaire journalier de référence',
            number_format($b['salaireJournalier'], 3, '.', "\u{202F}") . ' TND/j');
        $this->pdfSep($pdf);
        $this->pdfLig($pdf, "Salaire brut  ({$b['nbJoursTravailles']} jours)",
            number_format($b['salaireBrut'], 3, '.', "\u{202F}") . ' TND', 0, true);
        $this->pdfLig($pdf, "CNSS employé ({$b['tauxCNSSEmp']} %)",
            '−  ' . number_format($b['cnssEmploye'], 3, '.', "\u{202F}") . ' TND', 1);
        $this->pdfLig($pdf, 'IRPP barème mensuel 2024',
            '−  ' . number_format($b['irpp'], 3, '.', "\u{202F}") . ' TND', 1);
        $this->pdfSep($pdf);

        // ── Net à payer
        $pdf->SetFillColor(33, 77, 46);
        $pdf->Rect(18, $pdf->GetY(), 174, 13, 'F');
        $pdf->SetFont('dejavusans', 'B', 11);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(122, 13, '  Salaire NET à payer', 0, 0, 'L');
        $pdf->Cell(52, 13,
            number_format($b['salaireNet'], 3, '.', "\u{202F}") . ' TND', 0, 1, 'R');
        $pdf->Ln(3);

        // ── Charge employeur
        $pdf->SetFillColor(253, 243, 243);
        $pdf->Rect(18, $pdf->GetY(), 174, 13, 'F');
        $pdf->SetFont('dejavusans', '', 9);
        $pdf->SetTextColor(100, 40, 40);
        $pdf->Cell(122, 13, '  Charge totale employeur  (brut + CNSS patronale)', 0, 0, 'L');
        $pdf->SetFont('dejavusans', 'B', 9);
        $pdf->Cell(52, 13,
            number_format($b['coutTotalEmpl'], 3, '.', "\u{202F}") . ' TND', 0, 1, 'R');
        $pdf->SetFont('dejavusans', '', 8);
        $pdf->SetTextColor(130, 130, 130);
        $pdf->SetX(22);
        $pdf->Cell(0, 5,
            "dont CNSS employeur ({$b['tauxCNSSEmpr']} %) = " .
            number_format($b['cnssEmployeur'], 3, '.', "\u{202F}") . ' TND', 0, 1);
        $pdf->Ln(12);

        // ── Mention légale
        $pdf->SetFont('dejavusans', 'I', 7);
        $pdf->SetTextColor(150, 150, 150);
        $pdf->MultiCell(0, 4,
            "Document généré automatiquement par ARDHI Finance RH le " . date('d/m/Y à H:i') . ".\n" .
            "Taux CNSS : Décret 2024 · IRPP : barème en vigueur 2024. " .
            "Ce document est informatif. Rapprochez-vous d'un expert-comptable pour validation légale.",
            0, 'L');

        // ── Footer bande verte
        $pdf->SetFillColor(33, 77, 46);
        $pdf->Rect(0, 277, 210, 20, 'F');
        $pdf->SetFont('dejavusans', '', 7);
        $pdf->SetTextColor(163, 201, 175);
        $pdf->SetY(281);
        $pdf->Cell(0, 4, 'ARDHI · Gestion Agricole Intelligente  —  ardhi.tn', 0, 1, 'C');

        $filename = sprintf('bulletin_%s_%s_%s_%s.pdf',
            $employe->getNom(), $employe->getPrenom(), $b['mois'], $annee);

        return new Response($pdf->Output($filename, 'I'), 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => "inline; filename=\"{$filename}\"",
        ]);
    }

    // ── Coût d'une tâche (JSON) ──────────────────────────────────────
    #[Route('/tache/{id}/cout', name: 'tache_cout', methods: ['GET'])]
    public function coutTache(int $id): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $tache    = $this->tacheRepo->find($id);
        $employes = $this->employeRepo->findByAgriculteur($result);

        if (!$tache) return new JsonResponse(['error' => 'Tâche introuvable'], 404);

        $a = $this->financeService->analyserCoutTache($tache, $employes);

        return new JsonResponse([
            'titre'        => $tache->getTitre(),
            'categorie'    => $a['categorie'],
            'nomEmploye'   => $a['nomEmploye'],
            'nbJours'      => $a['nbJours'],
            'salaireJ'     => $a['salaireJ'],
            'coutSalaire'  => $a['coutSalaire'],
            'coutMateriel' => $a['coutMateriel'],
            'budgetPrevu'  => $a['budgetPrevu'],
            'coutReel'     => $a['coutReel'],
            'ecart'        => $a['ecart'],
            'pctEcart'     => $a['pctEcart'],
            'statut'       => $a['statut'],
            'enSurbudget'  => $a['enSurbudget'],
        ]);
    }

    // ── Helpers PDF ───────────────────────────────────────────────────

    private function pdfLig(\TCPDF $pdf, string $label, string $val,
                             int $rouge = 0, bool $gras = false): void
    {
        $pdf->SetFont('dejavusans', $gras ? 'B' : '', 9);
        if ($rouge) $pdf->SetTextColor(160, 40, 40);
        else $pdf->SetTextColor(40, 40, 40);
        $pdf->Cell(130, 8, '  ' . $label, 0, 0, 'L');
        $pdf->Cell(44,  8, $val,           0, 1, 'R');
    }

    private function pdfSep(\TCPDF $pdf): void
    {
        $pdf->SetDrawColor(200, 230, 210);
        $pdf->Line(18, $pdf->GetY(), 192, $pdf->GetY());
        $pdf->Ln(1);
    }
}