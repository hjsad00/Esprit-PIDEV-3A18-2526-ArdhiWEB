<?php

namespace App\Controller\EmployeTache;

use App\Repository\EmployeTache\EmployeRepository;
use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\PerformanceService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * 📊 Contrôleur de performance des employés
 *
 * Traduction exacte de PerformanceController.java du desktop.
 * Gère :
 *   - La page principale du classement (tableau)
 *   - Les détails d'un employé en JSON (modal AJAX)
 *   - Les statistiques globales (KPI cards)
 */
#[Route('/employes/performance')]
class PerformanceController extends AbstractController
{
    public function __construct(
        private AgriculteurContextService $ctx,
        private PerformanceService        $performanceService,
        private EmployeRepository         $employeRepo,
    ) {}

    // ── Garde d'accès (identique aux autres contrôleurs du module) ────

    private function checkAccess(): int|Response
    {
        if (!$this->ctx->hasAccess()) {
            $this->addFlash('warning',
                '⛔ Ce service est dédié uniquement aux administrateurs et aux agriculteurs.');
            return $this->redirectToRoute('app_home');
        }

        $idAgriculteur = $this->ctx->getActiveAgriculteurId();

        if ($idAgriculteur === null) {
            $this->addFlash('info', 'Veuillez sélectionner un agriculteur à superviser.');
            return $this->redirectToRoute('admin_agriculteurs_employe');
        }

        return $idAgriculteur;
    }

    // ══════════════════════════════════════════════════════════════════
    //  PAGE PRINCIPALE — CLASSEMENT
    //  Identique à initialize() + loadPerformances() + updateStatistics()
    //  du PerformanceController.java
    // ══════════════════════════════════════════════════════════════════

    #[Route('', name: 'employe_performance', methods: ['GET'])]
    public function index(): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        // Charger le classement (identique à loadPerformances() Java)
        $classement = $this->performanceService->getClassement($idAgriculteur);

        // Calculer les statistiques globales (identique à updateStatistics() Java)
        $stats = $this->performanceService->getStatistiquesGlobales($classement);

        // Top 3 (identique à updateTopPerformers() dans EmployeController.java)
        $top3 = array_slice($classement, 0, 3);

        return $this->render('EmployeTache/employe/performance.html.twig', [
            'classement'       => $classement,
            'stats'            => $stats,
            'top3'             => $top3,
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    //  DÉTAILS D'UN EMPLOYÉ EN JSON
    //  Identique à handleVoirDetails() du PerformanceController.java
    //  Appelé en AJAX depuis le bouton "Voir détails" dans le tableau
    // ══════════════════════════════════════════════════════════════════

    #[Route('/{id}/details', name: 'employe_performance_detail', methods: ['GET'])]
    public function detail(int $id): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['error' => 'Accès refusé.'], 403);
        }
        $idAgriculteur = $result;

        // Vérifier que l'employé appartient à cet agriculteur
        $employe = $this->employeRepo->find($id);
        if (!$employe || $employe->getIdAgriculteur() !== $idAgriculteur) {
            return new JsonResponse(['error' => 'Employé introuvable.'], 404);
        }

        // Calculer la performance
        $perf = $this->performanceService->calculatePerformance($id);
        $perf['nomEmploye'] = $employe->getNomComplet();

        // Retourner en JSON (identique aux données affichées dans l'Alert Java)
        return new JsonResponse($perf);
    }
}