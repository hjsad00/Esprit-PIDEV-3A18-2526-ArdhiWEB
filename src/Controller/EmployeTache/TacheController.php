<?php

namespace App\Controller\EmployeTache;

use App\Entity\EmployeTache\Tache;
use App\Repository\EmployeTache\TacheRepository;
use App\Repository\EmployeTache\EmployeRepository;
use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\GoogleCalendarService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/taches')]
class TacheController extends AbstractController
{
    private const TRIS_VALIDES = ['id','titre','statut','priorite','dateDebut','dateFin','categorie'];

    public function __construct(
        private AgriculteurContextService $ctx,
        private GoogleCalendarService     $gcal,
    ) {}

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

    // ── Liste ─────────────────────────────────────────────────────────

    #[Route('', name: 'tache_index')]
    public function index(TacheRepository $repo, EmployeRepository $empRepo,
                          Request $request): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $search    = $request->query->get('search', '');
        $statut    = $request->query->get('statut', 'Tous');
        $priorite  = $request->query->get('priorite', 'Toutes');
        $categorie = $request->query->get('categorie', 'Toutes');
        $tri       = $request->query->get('tri', 'dateDebut');
        $direction = $request->query->get('direction', 'asc');

        if (!in_array($tri, self::TRIS_VALIDES, true)) $tri = 'dateDebut';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        $taches = $repo->findFiltreeTrie($idAgriculteur, $search, $statut, $priorite, $categorie, $tri, $direction);
        $kpis   = $repo->countByStatut($idAgriculteur);

        $employes    = $empRepo->findActifsByAgriculteur($idAgriculteur);
        $mapEmployes = [];
        foreach ($employes as $emp) {
            $mapEmployes[$emp->getId()] = $emp->getNomComplet();
        }

        return $this->render('EmployeTache/tache/index.html.twig', [
            'taches'           => $taches,
            'kpis'             => $kpis,
            'employes'         => $employes,
            'mapEmployes'      => $mapEmployes,
            'search'           => $search,
            'statut'           => $statut,
            'priorite'         => $priorite,
            'categorie'        => $categorie,
            'tri'              => $tri,
            'direction'        => $direction,
            'statuts'          => ['Tous', ...Tache::STATUTS],
            'priorites'        => ['Toutes', 'Basse', 'Moyenne', 'Haute', 'Critique'],
            'categories'       => ['Toutes', ...Tache::CATEGORIES],
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── Création ─────────────────────────────────────────────────────

    #[Route('/new', name: 'tache_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em,
                        ValidatorInterface $validator, EmployeRepository $empRepo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $errors   = [];
        $old      = [];
        $employes = $empRepo->findActifsByAgriculteur($idAgriculteur);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tache_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('tache_new');
            }

            $old    = $request->request->all();
            $data   = $this->extractFormData($request);
            $errors = $this->validerDonnees($data, $validator);

            if (empty($errors)) {
                $tache = new Tache();
                $this->hydraterTache($tache, $data);
                $tache->setIdAgriculteur($idAgriculteur);

                $em->persist($tache);
                $em->flush();

                $this->addFlash('success', '✅ Tâche "' . $tache->getTitre() . '" créée.');
                return $this->redirectToRoute('tache_index');
            }
        }

        return $this->render('EmployeTache/tache/form.html.twig', [
            'page_title'       => 'Ajouter une Tâche',
            'tache'            => null,
            'employes'         => $employes,
            'errors'           => $errors,
            'old'              => $old,
            'csrf_token_id'    => 'tache_form',
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── Modification ──────────────────────────────────────────────────

    #[Route('/{id}/edit', name: 'tache_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em,
                         ValidatorInterface $validator, TacheRepository $repo,
                         EmployeRepository $empRepo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $tache = $repo->find($id);
        if (!$tache || $tache->getIdAgriculteur() !== $idAgriculteur) {
            throw $this->createNotFoundException('Tâche introuvable.');
        }

        $errors   = [];
        $old      = [];
        $employes = $empRepo->findActifsByAgriculteur($idAgriculteur);

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('tache_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('tache_edit', ['id' => $id]);
            }

            $old    = $request->request->all();
            $data   = $this->extractFormData($request);
            $errors = $this->validerDonnees($data, $validator);

            if (empty($errors)) {
                $this->hydraterTache($tache, $data);
                $em->flush();

                $this->addFlash('success', '✅ Tâche "' . $tache->getTitre() . '" modifiée.');
                return $this->redirectToRoute('tache_index');
            }
        }

        return $this->render('EmployeTache/tache/form.html.twig', [
            'page_title'       => 'Modifier — ' . $tache->getTitre(),
            'tache'            => $tache,
            'employes'         => $employes,
            'errors'           => $errors,
            'old'              => $old,
            'csrf_token_id'    => 'tache_form',
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── Suppression ───────────────────────────────────────────────────

    #[Route('/{id}/delete', name: 'tache_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em,
                           TacheRepository $repo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $tache = $repo->find($id);
        if ($tache && $tache->getIdAgriculteur() === $idAgriculteur
            && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $titre = $tache->getTitre();
            $em->remove($tache);
            $em->flush();
            $this->addFlash('success', '🗑️ Tâche "' . $titre . '" supprimée.');
        }
        return $this->redirectToRoute('tache_index');
    }

    // ── Changement de statut rapide ───────────────────────────────────

    #[Route('/{id}/statut/{statut}', name: 'tache_statut', methods: ['POST'])]
    public function changerStatut(int $id, string $statut, EntityManagerInterface $em,
                                   TacheRepository $repo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $tache = $repo->find($id);
        if ($tache && $tache->getIdAgriculteur() === $idAgriculteur
            && in_array($statut, Tache::STATUTS, true)) {
            $tache->setStatut($statut);
            $em->flush();
            $this->addFlash('success', '"' . $tache->getTitre() . '" → ' . $statut);
        }
        return $this->redirectToRoute('tache_index');
    }

    // ── Export PDF ────────────────────────────────────────────────────

    #[Route('/pdf/export', name: 'tache_pdf', methods: ['GET'])]
    public function exportPdf(TacheRepository $repo, EmployeRepository $empRepo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $taches = $repo->findByAgriculteur($idAgriculteur);
        $employes = $empRepo->findActifsByAgriculteur($idAgriculteur);
        $mapEmployes = [];
        foreach ($employes as $emp) {
            $mapEmployes[$emp->getId()] = $emp->getNomComplet();
        }

        $kpis = $repo->countByStatut($idAgriculteur);
        $enRetard = $repo->countEnRetard($idAgriculteur);

        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Ardhi');
        $pdf->SetAuthor('Ardhi');
        $pdf->SetTitle('Liste des Tâches');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(8, 8, 8);
        $pdf->SetAutoPageBreak(true, 12);
        $pdf->AddPage();

        // Header green bar
        $pdf->SetFillColor(34, 120, 60);
        $pdf->Rect(0, 0, 297, 28, 'F');
        $pdf->SetFillColor(39, 174, 96);
        $pdf->Rect(0, 24, 297, 4, 'F');

        $pdf->SetFont('helvetica', 'B', 22);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetY(4);
        $pdf->Cell(0, 10, 'LISTE DES TÂCHES', 0, 1, 'C');
        $pdf->SetFont('helvetica', '', 10);
        $date = (new \DateTime())->format('d/m/Y H:i');
        $pdf->Cell(0, 6, 'Généré le : ' . $date, 0, 1, 'C');
        $pdf->Ln(8);

        // KPI boxes
        $pdf->SetTextColor(50, 50, 50);
        $startX = 18;
        $boxW = 50;
        $gap = 5;
        $kpiData = [
            ['label' => 'Total tâches',  'value' => $kpis['total'],     'r' => 74, 'g' => 124, 'b' => 89],
            ['label' => 'En cours',      'value' => $kpis['en_cours'],  'r' => 46, 'g' => 139, 'b' => 87],
            ['label' => 'Terminées',     'value' => $kpis['terminees'], 'r' => 39, 'g' => 174, 'b' => 96],
            ['label' => 'En attente',    'value' => $kpis['en_attente'],'r' => 100,'g' => 160, 'b' => 120],
            ['label' => 'En retard',     'value' => $enRetard,          'r' => 180,'g' => 60,  'b' => 60],
        ];

        $y = $pdf->GetY();
        foreach ($kpiData as $i => $kpi) {
            $x = $startX + $i * ($boxW + $gap);
            $pdf->SetFillColor($kpi['r'], $kpi['g'], $kpi['b']);
            $pdf->RoundedRect($x, $y, $boxW, 16, 3, '1111', 'F');
            $pdf->SetFont('helvetica', 'B', 16);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->SetXY($x, $y + 1);
            $pdf->Cell($boxW, 8, $kpi['value'], 0, 0, 'C');
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetXY($x, $y + 8);
            $pdf->Cell($boxW, 6, $kpi['label'], 0, 0, 'C');
        }
        $pdf->Ln(22);

        // Table header
        $pdf->SetFont('helvetica', 'B', 9);
        $pdf->SetFillColor(34, 120, 60);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetDrawColor(34, 120, 60);

        $cols = [
            ['w' => 12, 'h' => 'ID'],    ['w' => 42, 'h' => 'Titre'],
            ['w' => 50, 'h' => 'Description'], ['w' => 25, 'h' => 'Statut'],
            ['w' => 22, 'h' => 'Priorité'],    ['w' => 28, 'h' => 'Catégorie'],
            ['w' => 25, 'h' => 'Début'],       ['w' => 25, 'h' => 'Fin'],
            ['w' => 38, 'h' => 'Employé'],     ['w' => 14, 'h' => 'Retard'],
        ];
        foreach ($cols as $col) {
            $pdf->Cell($col['w'], 8, $col['h'], 1, 0, 'C', true);
        }
        $pdf->Ln();

        // Table body
        $pdf->SetFont('helvetica', '', 8);
        $pdf->SetTextColor(30, 30, 30);
        $pdf->SetDrawColor(200, 220, 200);
        $rowIndex = 0;

        foreach ($taches as $tache) {
            $pdf->SetFillColor($rowIndex % 2 === 0 ? 245 : 255, $rowIndex % 2 === 0 ? 250 : 255, $rowIndex % 2 === 0 ? 245 : 255);

            $pdf->Cell(12, 7, $tache->getId(), 1, 0, 'C', true);
            $pdf->Cell(42, 7, mb_strimwidth($tache->getTitre(), 0, 28, '...'), 1, 0, 'L', true);
            $pdf->Cell(50, 7, mb_strimwidth($tache->getDescription() ?? '-', 0, 35, '...'), 1, 0, 'L', true);

            $stColor = match($tache->getStatut()) {
                'En attente' => [100, 160, 120], 'En cours' => [46, 139, 87],
                'Terminé' => [34, 120, 60], 'Validé' => [26, 188, 156],
                'Annulé' => [180, 60, 60], default => [100, 100, 100],
            };
            $pdf->SetTextColor($stColor[0], $stColor[1], $stColor[2]);
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(25, 7, $tache->getStatut(), 1, 0, 'C', true);
            $pdf->SetTextColor(30, 30, 30);
            $pdf->SetFont('helvetica', '', 8);

            $pdf->Cell(22, 7, Tache::PRIORITES[$tache->getPriorite()] ?? 'Moyenne', 1, 0, 'C', true);
            $pdf->Cell(28, 7, $tache->getCategorie() ?? '-', 1, 0, 'C', true);
            $pdf->Cell(25, 7, $tache->getDateDebut() ? $tache->getDateDebut()->format('d/m/Y') : '-', 1, 0, 'C', true);
            $pdf->Cell(25, 7, $tache->getDateFin() ? $tache->getDateFin()->format('d/m/Y') : '-', 1, 0, 'C', true);
            $pdf->Cell(38, 7, $mapEmployes[$tache->getIdEmploye()] ?? '-', 1, 0, 'L', true);

            if ($tache->isEnRetard()) {
                $pdf->SetFillColor(255, 220, 220);
                $pdf->SetTextColor(180, 40, 40);
                $pdf->SetFont('helvetica', 'B', 8);
                $pdf->Cell(14, 7, 'OUI', 1, 1, 'C', true);
                $pdf->SetTextColor(30, 30, 30);
                $pdf->SetFont('helvetica', '', 8);
            } else {
                $pdf->Cell(14, 7, '-', 1, 1, 'C', true);
            }
            $rowIndex++;
        }

        // Footer
        $pdf->Ln(5);
        $pdf->SetFillColor(39, 174, 96);
        $pdf->Rect(8, $pdf->GetY(), 281, 1, 'F');
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(130, 130, 130);
        $pdf->Cell(0, 5, 'Ardhi - Gestion Agricole Intelligente | ' . $date, 0, 1, 'C');

        return new Response(
            $pdf->Output('taches_' . date('Ymd_His') . '.pdf', 'I'),
            200,
            ['Content-Type' => 'application/pdf']
        );
    }

    // ── Calendrier ────────────────────────────────────────────────────

    #[Route('/calendrier', name: 'tache_calendrier', methods: ['GET'])]
    public function calendrier(TacheRepository $repo, EmployeRepository $empRepo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $taches = $repo->findByAgriculteur($idAgriculteur);

        $employes    = $empRepo->findActifsByAgriculteur($idAgriculteur);
        $mapEmployes = [];
        foreach ($employes as $emp) {
            $mapEmployes[$emp->getId()] = $emp->getNomComplet();
        }

        // Formater les tâches en JSON pour FullCalendar
        $events = [];
        $colorMap = [
            4 => '#e74c3c', // Critique — rouge
            3 => '#f39c12', // Haute    — orange
            2 => '#3498db', // Moyenne  — bleu
            1 => '#27ae60', // Basse    — vert
        ];
        $statutColorMap = [
            'En attente' => '#856404',
            'En cours'   => '#004085',
            'Terminé'    => '#155724',
            'Validé'     => '#0c5460',
            'Annulé'     => '#721c24',
        ];

        foreach ($taches as $tache) {
            $debut = $tache->getDateDebut() ?? new \DateTime('today');
            $fin   = $tache->getDateFin()   ?? $debut;
            // FullCalendar all-day : end exclusif → +1 jour
            $finPlusUn = (clone \DateTime::createFromInterface($fin))->modify('+1 day');

            $events[] = [
                'id'              => $tache->getId(),
                'title'           => $tache->getTitre(),
                'start'           => $debut->format('Y-m-d'),
                'end'             => $finPlusUn->format('Y-m-d'),
                'backgroundColor' => $colorMap[$tache->getPriorite()] ?? '#4a7c59',
                'borderColor'     => $colorMap[$tache->getPriorite()] ?? '#4a7c59',
                'extendedProps'   => [
                    'statut'    => $tache->getStatut(),
                    'priorite'  => $tache->getPrioriteLabel(),
                    'categorie' => $tache->getCategorie() ?? '—',
                    'employe'   => $mapEmployes[$tache->getIdEmploye()] ?? '—',
                    'retard'    => $tache->isEnRetard(),
                    'editUrl'   => '/taches/' . $tache->getId() . '/edit',
                ],
            ];
        }

        return $this->render('EmployeTache/tache/calendrier.html.twig', [
            'events'           => json_encode($events),
            'taches'           => $taches,
            'gcal_id'          => $this->gcal->getCalendarId(),
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── Synchronisation Google Calendar ───────────────────────────────

    #[Route('/calendrier/sync', name: 'tache_calendrier_sync', methods: ['GET', 'POST'])]
    public function syncGoogleCalendar(Request $request, TacheRepository $repo): JsonResponse
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé.'], 403);
        }
        $idAgriculteur = $result;

        // ── GET : vérification de connexion (comme Java checkTask en arrière-plan) ──
        if ($request->isMethod('GET')) {
            $connected = $this->gcal->connecter();
            return new JsonResponse([
                'connected' => $connected,
                'calName'   => $connected ? $this->gcal->getNomCalendrier() : null,
                'error'     => $connected ? null : $this->gcal->getLastError(),
            ]);
        }

        // ── POST : push des tâches vers Google Calendar ──────────────────
        if (!$this->gcal->connecter()) {
            return new JsonResponse([
                'success' => false,
                'message' => '❌ Connexion Google Calendar impossible : ' . $this->gcal->getLastError(),
            ], 500);
        }

        $taches  = $repo->findByAgriculteur($idAgriculteur);
        $created = 0;
        $errors  = 0;
        $details = [];

        foreach ($taches as $tache) {
            $eventId = $this->gcal->creerEvenement($tache);
            if ($eventId) {
                $created++;
                $details[] = '✅ ' . $tache->getTitre();
            } else {
                $errors++;
                $details[] = '❌ ' . $tache->getTitre() . ' — ' . $this->gcal->getLastError();
            }
        }

        $calName = $this->gcal->getNomCalendrier() ?? $this->gcal->getCalendarId();

        return new JsonResponse([
            'success' => $errors === 0,
            'message' => sprintf(
                'Synchronisation vers « %s » terminée : %d envoyée(s), %d erreur(s).',
                $calName, $created, $errors
            ),
            'created'  => $created,
            'errors'   => $errors,
            'details'  => $details,
            'calendar' => $calName,
        ]);
    }

    // ── Statistiques ──────────────────────────────────────────────────

    #[Route('/statistiques', name: 'tache_statistiques', methods: ['GET'])]
    public function statistiques(TacheRepository $repo, EmployeRepository $empRepo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $kpis = $repo->countByStatut($idAgriculteur);
        $enRetard = $repo->countEnRetard($idAgriculteur);
        $nonAssignees = $repo->countNonAssignees($idAgriculteur);
        $tauxCompletion = $kpis['total'] > 0
            ? round(($kpis['terminees'] / $kpis['total']) * 100, 1) : 0;

        $statuts   = $repo->countDetailStatut($idAgriculteur);
        $priorites = $repo->countByPriorite($idAgriculteur);

        $empData = $repo->countByEmploye($idAgriculteur);
        $employes = $empRepo->findActifsByAgriculteur($idAgriculteur);
        $mapEmployes = [];
        foreach ($employes as $emp) {
            $mapEmployes[$emp->getId()] = $emp->getNomComplet();
        }
        $employeStats = [];
        foreach ($empData as $ed) {
            $employeStats[] = [
                'nom'   => $mapEmployes[$ed['idEmploye']] ?? 'Inconnu',
                'total' => $ed['total'],
            ];
        }

        $dateData = $repo->countByDate($idAgriculteur);
        $evolution = [];
        foreach ($dateData as $dd) {
            $evolution[] = [
                'date'  => $dd['dateDebut'] instanceof \DateTimeInterface
                    ? $dd['dateDebut']->format('d/m/Y') : (string) $dd['dateDebut'],
                'total' => $dd['total'],
            ];
        }

        $categories = $repo->countByCategorie($idAgriculteur);

        return $this->render('EmployeTache/tache/statistiques.html.twig', [
            'kpis'             => $kpis,
            'enRetard'         => $enRetard,
            'nonAssignees'     => $nonAssignees,
            'tauxCompletion'   => $tauxCompletion,
            'statuts'          => $statuts,
            'priorites'        => $priorites,
            'employeStats'     => $employeStats,
            'evolution'        => $evolution,
            'categories'       => $categories,
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── Validation ────────────────────────────────────────────────────

    private function validerDonnees(array $data, ValidatorInterface $validator): array
    {
        $errors = [];

        $v = $validator->validate($data['titre'], [
            new Assert\NotBlank(message: 'Le titre est obligatoire.'),
            new Assert\Length(max: 200, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.'),
        ]);
        if (count($v)) $errors['titre'] = $v[0]->getMessage();

        $v = $validator->validate($data['description'], [
            new Assert\NotBlank(message: 'La description est obligatoire.'),
        ]);
        if (count($v)) $errors['description'] = $v[0]->getMessage();

        $v = $validator->validate($data['statut'], [
            new Assert\NotBlank(message: 'Le statut est obligatoire.'),
            new Assert\Choice(choices: Tache::STATUTS, message: 'Statut invalide.'),
        ]);
        if (count($v)) $errors['statut'] = $v[0]->getMessage();

        $v = $validator->validate($data['priorite'], [
            new Assert\NotBlank(message: 'La priorité est obligatoire.'),
            new Assert\Choice(choices: [1, 2, 3, 4], message: 'Priorité invalide.'),
        ]);
        if (count($v)) $errors['priorite'] = $v[0]->getMessage();

        if ($data['dateDebut'] === null) {
            $errors['dateDebut'] = 'La date de début est obligatoire.';
        }

        if ($data['dateDebut'] !== null && $data['dateFin'] !== null
            && $data['dateFin'] < $data['dateDebut']) {
            $errors['dateFin'] = '⚠ Date fin < date début.';
        }

        if ($data['idEmploye'] === null) {
            $errors['idEmploye'] = "L'employé est obligatoire.";
        }

        return $errors;
    }

    private function extractFormData(Request $r): array
    {
        $dateDebut = null;
        $dateFin   = null;
        if ($r->request->get('dateDebut')) {
            try { $dateDebut = new \DateTime($r->request->get('dateDebut')); } catch (\Exception) {}
        }
        if ($r->request->get('dateFin')) {
            try { $dateFin = new \DateTime($r->request->get('dateFin')); } catch (\Exception) {}
        }

        return [
            'titre'       => trim($r->request->get('titre', '')),
            'description' => trim($r->request->get('description', '')),
            'statut'      => $r->request->get('statut', Tache::STATUT_EN_ATTENTE),
            'priorite'    => $r->request->get('priorite') ? (int)$r->request->get('priorite') : null,
            'categorie'   => $r->request->get('categorie') ?: 'Plantation',
            'dateDebut'   => $dateDebut,
            'dateFin'     => $dateFin,
            'idEmploye'   => $r->request->get('idEmploye') ? (int)$r->request->get('idEmploye') : null,
        ];
    }

    private function hydraterTache(Tache $tache, array $data): void
    {
        $tache->setTitre($data['titre']);
        $tache->setDescription($data['description'] ?: null);
        $tache->setStatut($data['statut']);
        $tache->setPriorite($data['priorite']);
        $tache->setCategorie($data['categorie']);
        $tache->setDateDebut($data['dateDebut']);
        $tache->setDateFin($data['dateFin']);
        $tache->setIdEmploye($data['idEmploye']);
    }
}