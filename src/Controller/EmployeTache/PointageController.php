<?php

namespace App\Controller\EmployeTache;

use App\Entity\EmployeTache\Pointage;
use App\Repository\EmployeTache\EmployeRepository;
use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\PointageService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/pointage', name: 'pointage_')]
class PointageController extends AbstractController
{
    public function __construct(
        private AgriculteurContextService $ctx,
        private PointageService           $pointageService,
        private EmployeRepository         $employeRepo,
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

    // ── Dashboard pointage (superviseur) ─────────────────────────────

    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;

        $data = $this->pointageService->getDashboardPointage($result);

        return $this->render('EmployeTache/pointage/dashboard.html.twig', array_merge($data, [
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]));
    }

    // ── Page mobile pointage (accès depuis QR code ou direct) ────────

    #[Route('/mobile/{idEmploye}', name: 'mobile', methods: ['GET'])]
    public function mobile(int $idEmploye): Response
    {
        $employe = $this->employeRepo->find($idEmploye);
        if (!$employe) {
            throw $this->createNotFoundException('Employé introuvable.');
        }

        return $this->render('EmployeTache/pointage/mobile.html.twig', [
            'employe' => $employe,
        ]);
    }

    // ── API : Enregistrer un pointage GPS ─────────────────────────────

    #[Route('/api/pointer', name: 'api_pointer', methods: ['POST'])]
    public function pointer(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $idEmploye     = (int)  ($data['idEmploye']     ?? 0);
        $idAgriculteur = (int)  ($data['idAgriculteur'] ?? 0);
        $type          = (string)($data['type']          ?? Pointage::TYPE_ARRIVEE);
        $lat           = (float) ($data['latitude']      ?? 0.0);
        $lng           = (float) ($data['longitude']     ?? 0.0);
        $source        = (string)($data['source']        ?? Pointage::SOURCE_GPS);

        if ($idEmploye === 0 || $idAgriculteur === 0 || ($lat === 0.0 && $lng === 0.0)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Données GPS manquantes ou incomplètes.',
            ], 400);
        }

        if (!in_array($type, [Pointage::TYPE_ARRIVEE, Pointage::TYPE_DEPART], true)) {
            $type = Pointage::TYPE_ARRIVEE;
        }

        $result = $this->pointageService->enregistrerPointage(
            $idEmploye, $idAgriculteur, $type, $lat, $lng, $source
        );

        return new JsonResponse($result, $result['success'] ? 200 : 400);
    }

    // ── API : Configurer les coordonnées de la ferme ──────────────────

    #[Route('/api/config-ferme', name: 'api_config_ferme', methods: ['POST'])]
    public function configFerme(Request $request): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['success' => false], 403);
        }
        $idAgriculteur = $result;

        $data  = json_decode($request->getContent(), true) ?? [];
        $lat   = (float) ($data['latitude']  ?? 0.0);
        $lng   = (float) ($data['longitude'] ?? 0.0);
        $rayon = (int)   ($data['rayon']     ?? 500);
        $nom   = (string)($data['nom']       ?? 'Ma ferme');

        if ($lat === 0.0 || $lng === 0.0) {
            return new JsonResponse(['success' => false, 'message' => 'Coordonnées invalides.'], 400);
        }

        try {
            $conn = $this->getDoctrine()->getConnection();
            $conn->executeStatement(
                'INSERT INTO ferme_config (id_agriculteur, latitude, longitude, rayon_validation, nom_ferme)
                 VALUES (?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE latitude=VALUES(latitude), longitude=VALUES(longitude),
                 rayon_validation=VALUES(rayon_validation), nom_ferme=VALUES(nom_ferme)',
                [$idAgriculteur, $lat, $lng, $rayon, $nom]
            );
            return new JsonResponse(['success' => true, 'message' => 'Configuration enregistrée.']);
        } catch (\Throwable $e) {
            return new JsonResponse(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }
}