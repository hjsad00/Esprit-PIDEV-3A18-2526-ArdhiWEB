<?php

namespace App\Controller\EmployeTache;

use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;
use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\FinanceRHService;
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
    ) {}

    private function checkAccess(): int|Response
    {
        if (!$this->ctx->hasAccess()) {
            $this->addFlash('warning', '⛔ Accès réservé aux administrateurs et agriculteurs.');
            return $this->redirectToRoute('app_home');
        }
        $id = $this->ctx->getActiveAgriculteurId();
        if ($id === null) {
            $this->addFlash('info', 'Veuillez sélectionner un agriculteur à superviser.');
            return $this->redirectToRoute('admin_agriculteurs_employe');
        }
        return $id;
    }

    // ── Dashboard financier principal ─────────────────────────────────

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $data = $this->financeService->getDashboardFinancier($idAgriculteur);

        return $this->render('EmployeTache/finance/dashboard.html.twig', array_merge($data, [
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]));
    }

    // ── Bulletin de paie d'un employé (JSON pour modal) ──────────────

    #[Route('/bulletin/{id}', name: 'bulletin', methods: ['GET'])]
    public function bulletin(int $id, Request $request): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $mois  = (int) $request->query->get('mois',  date('n'));
        $annee = (int) $request->query->get('annee', date('Y'));

        $bulletin = $this->financeService->genererBulletinPaie($id, $mois, $annee);
        if (empty($bulletin)) {
            return new JsonResponse(['error' => 'Employé introuvable'], 404);
        }

        // Sérialiser manuellement (l'objet Employe n'est pas JSON-serializable)
        $emp = $bulletin['employe'];
        return new JsonResponse([
            'employe'          => [
                'id'     => $emp->getId(),
                'nom'    => $emp->getNom(),
                'prenom' => $emp->getPrenom(),
                'poste'  => $emp->getPoste(),
                'email'  => $emp->getEmail(),
            ],
            'mois'             => $bulletin['mois'],
            'annee'            => $bulletin['annee'],
            'nbJoursTravailles'=> $bulletin['nbJoursTravailles'],
            'salaireJournalier'=> $bulletin['salaireJournalier'],
            'salaireBrut'      => $bulletin['salaireBrut'],
            'cnssEmploye'      => $bulletin['cnssEmploye'],
            'cnssEmployeur'    => $bulletin['cnssEmployeur'],
            'irpp'             => $bulletin['irpp'],
            'salaireNet'       => $bulletin['salaireNet'],
            'coutTotalEmpl'    => $bulletin['coutTotalEmpl'],
            'tauxCNSSEmp'      => $bulletin['tauxCNSSEmp'],
            'tauxCNSSEmpr'     => $bulletin['tauxCNSSEmpr'],
            'typeContrat'      => $bulletin['typeContrat'],
        ]);
    }

    // ── Analyse coût d'une tâche (JSON) ──────────────────────────────

    #[Route('/tache/{id}/cout', name: 'tache_cout', methods: ['GET'])]
    public function coutTache(int $id): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }
        $idAgriculteur = $result;

        $tache    = $this->tacheRepo->find($id);
        $employes = $this->employeRepo->findByAgriculteur($idAgriculteur);

        if (!$tache) {
            return new JsonResponse(['error' => 'Tâche introuvable'], 404);
        }

        $analyse = $this->financeService->analyserCoutTache($tache, $employes);

        return new JsonResponse([
            'titre'       => $tache->getTitre(),
            'categorie'   => $analyse['categorie'],
            'nomEmploye'  => $analyse['nomEmploye'],
            'nbJours'     => $analyse['nbJours'],
            'salaireJ'    => $analyse['salaireJ'],
            'coutSalaire' => $analyse['coutSalaire'],
            'coutMateriel'=> $analyse['coutMateriel'],
            'budgetPrevu' => $analyse['budgetPrevu'],
            'coutReel'    => $analyse['coutReel'],
            'ecart'       => $analyse['ecart'],
            'pctEcart'    => $analyse['pctEcart'],
            'statut'      => $analyse['statut'],
            'enSurbudget' => $analyse['enSurbudget'],
        ]);
    }
}