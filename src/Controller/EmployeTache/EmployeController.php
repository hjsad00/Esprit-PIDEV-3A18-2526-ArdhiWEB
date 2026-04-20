<?php

namespace App\Controller\EmployeTache;

use App\Entity\EmployeTache\Employe;
use App\Repository\EmployeTache\EmployeRepository;
use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\AttestationMail;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;
use App\Service\EmployeTache\QrCodeService;
use App\Service\EmployeTache\FicheEmployePdfService;
use App\Service\EmployeTache\EmployeAutoInactifService;
use App\Service\EmployeTache\PerformanceService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/employes')]
class EmployeController extends AbstractController
{
    private const TRIS_VALIDES = ['id','nom','prenom','email','poste','actif','telephone'];

    public function __construct(
        private AgriculteurContextService $ctx,
        private EmployeAutoInactifService $autoInactif,
        private PerformanceService        $performanceService,
        private TranslatorInterface       $translator,
    ) {}

    // ── Garde d'accès commune ─────────────────────────────────────────

    /**
     * Vérifie l'accès et retourne l'ID agriculteur à utiliser.
     * CLIENT / AGRONOME → redirigé avec message.
     * ADMIN sans supervision → redirigé vers liste agriculteurs.
     */
    private function checkAccess(): int|Response
    {
        if (!$this->ctx->hasAccess()) {
            // CLIENT ou AGRONOME → message et retour home
            $this->addFlash('warning',
                '⛔ Ce service est dédié uniquement aux administrateurs et aux agriculteurs.');
            return $this->redirectToRoute('app_home');
        }

        $idAgriculteur = $this->ctx->getActiveAgriculteurId();

        if ($idAgriculteur === null) {
            // Admin non en mode supervision → retour à la liste agriculteurs
            $this->addFlash('info', 'Veuillez sélectionner un agriculteur à superviser.');
            return $this->redirectToRoute('admin_agriculteurs_employe');
        }

        return $idAgriculteur;
    }

    // ── Liste ─────────────────────────────────────────────────────────

    #[Route('', name: 'employe_index')]
    public function index(EmployeRepository $repo, Request $request): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $search    = $request->query->get('search', '');
        $tri       = $request->query->get('tri', 'nom');
        $direction = $request->query->get('direction', 'asc');

        if (!in_array($tri, self::TRIS_VALIDES, true)) $tri = 'nom';
        $direction = $direction === 'desc' ? 'desc' : 'asc';

        // ✅ Synchronisation automatique à chaque affichage (identique à autoActiverEmployesAvecTaches()
        // du desktop Java : active/désactive l'employé selon ses tâches actives)
        $this->autoInactif->synchroniserStatuts($idAgriculteur);

        $employes = $repo->findByAgriculteurTrie($idAgriculteur, $tri, $direction, $search);

        // ✅ Calcul du score de performance pour chaque employé
        $performanceMap = [];
        $perfSort = [];
        foreach ($employes as $emp) {
            $perf = $this->performanceService->calculatePerformance($emp->getId());
            $performanceMap[$emp->getId()] = $perf;
            if ($emp->isActif() && $perf['totalTaches'] > 0 && $perf['score'] > 0) {
                $perfSort[] = ['emp' => $emp, 'perf' => $perf];
            }
        }
        usort($perfSort, fn($a, $b) => $b['perf']['score'] <=> $a['perf']['score']);
        $top3 = array_slice($perfSort, 0, 3);

        return $this->render('EmployeTache/employe/index.html.twig', [
            'employes'          => $employes,
            'search'            => $search,
            'tri'               => $tri,
            'direction'         => $direction,
            'total'             => count($employes),
            'total_actifs'      => count(array_filter($employes, fn($e) => $e->isActif())),
            'performanceMap'    => $performanceMap,
            'top3'              => $top3,
            'supervision_mode'  => $this->ctx->isSupervisionMode(),
            'nom_supervise'     => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── Création ──────────────────────────────────────────────────────

    #[Route('/new', name: 'employe_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em,
                        ValidatorInterface $validator, EmployeRepository $repo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $errors = [];
        $old    = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('employe_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('employe_new');
            }

            $old    = $request->request->all();
            $data   = $this->extractFormData($request);
            $errors = $this->validerDonnees($data, $validator, $repo);
            $errors = array_merge($errors, $this->validerPhoto($request->files->get('photo')));

            if (empty($errors)) {
                $employe = new Employe();
                $this->hydraterEmploye($employe, $data);
                $employe->setIdAgriculteur($idAgriculteur);

                $em->persist($employe);
                $em->flush();

                $employe->setQrCodeUnique($employe->genererQrCodeUnique());

                $photoFile = $request->files->get('photo');
                if ($photoFile) {
                    $path = $this->uploadPhoto($photoFile, $employe->getId());
                    if ($path) $employe->setPhotoPath($path);
                }
                $em->flush();

                $this->addFlash('success', '✅ ' . $this->translator->trans('common.success') . ': ' . $employe->getNomComplet());
                return $this->redirectToRoute('employe_index');
            }
        }

        return $this->render('EmployeTache/employe/form.html.twig', [
            'page_title'       => $this->translator->trans('common.add'),
            'employe'          => null,
            'errors'           => $errors,
            'old'              => $old,
            'csrf_token_id'    => 'employe_form',
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── Modification ──────────────────────────────────────────────────

    #[Route('/{id}/edit', name: 'employe_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em,
                         ValidatorInterface $validator, EmployeRepository $repo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $employe = $repo->find($id);
        if (!$employe || $employe->getIdAgriculteur() !== $idAgriculteur) {
            throw $this->createNotFoundException('Employé introuvable.');
        }

        $errors = [];
        $old    = [];

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('employe_form', $request->request->get('_token'))) {
                $this->addFlash('danger', 'Token de sécurité invalide.');
                return $this->redirectToRoute('employe_edit', ['id' => $id]);
            }

            $old    = $request->request->all();
            $data   = $this->extractFormData($request);
            $errors = $this->validerDonnees($data, $validator, $repo, $id);
            $errors = array_merge($errors, $this->validerPhoto($request->files->get('photo')));

            if (empty($errors)) {
                $this->hydraterEmploye($employe, $data);

                $photoFile = $request->files->get('photo');
                if ($photoFile) {
                    $path = $this->uploadPhoto($photoFile, $employe->getId());
                    if ($path) $employe->setPhotoPath($path);
                }
                $em->flush();

                $this->addFlash('success', '✅ ' . $this->translator->trans('common.success') . ': ' . $employe->getNomComplet());
                return $this->redirectToRoute('employe_index');
            }
        }

        return $this->render('EmployeTache/employe/form.html.twig', [
            'page_title'       => $this->translator->trans('common.edit') . ' — ' . $employe->getNomComplet(),
            'employe'          => $employe,
            'errors'           => $errors,
            'old'              => $old,
            'csrf_token_id'    => 'employe_form',
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── Suppression ───────────────────────────────────────────────────

    #[Route('/{id}/delete', name: 'employe_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em,
                           EmployeRepository $repo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $employe = $repo->find($id);
        if ($employe && $employe->getIdAgriculteur() === $idAgriculteur
            && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $nom = $employe->getNomComplet();
            $em->remove($employe);
            $em->flush();
            $this->addFlash('success', '🗑️ ' . $this->translator->trans('common.delete') . ': ' . $nom);
        }
        return $this->redirectToRoute('employe_index');
    }

    // ── Fiche ─────────────────────────────────────────────────────────

    #[Route('/{id<\d+>}', name: 'employe_show', methods: ['GET'])]
    public function show(int $id, EmployeRepository $repo, QrCodeService $qrService): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $employe = $repo->find($id);
        if (!$employe || $employe->getIdAgriculteur() !== $idAgriculteur) {
            throw $this->createNotFoundException('Employé introuvable.');
        }

        $qrUrl = $qrService->generateFicheUrl($employe->getId());
        $qrCodeUri = $qrService->generateQrCodeDataUri($qrUrl, 150);

        return $this->render('EmployeTache/employe/show.html.twig', [
            'employe'          => $employe,
            'qr_code_uri'      => $qrCodeUri,
            'qr_url'           => $qrUrl,
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── Attestation Mail ──────────────────────────────────────────────

    #[Route('/{id<\d+>}/attestation', name: 'employe_attestation', methods: ['GET'])]
    public function envoyerAttestation(int $id, EmployeRepository $repo, AttestationMail $mailService): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $employe = $repo->find($id);
        if (!$employe || $employe->getIdAgriculteur() !== $idAgriculteur) {
            throw $this->createNotFoundException('Employé introuvable.');
        }

        try {
            $mailService->envoyerAttestation($employe);
            $this->addFlash('success', '📧 ' . $this->translator->trans('employe.attestation_success'));
        } catch (\Exception $e) {
            $this->addFlash('danger', '❌ Erreur : ' . $e->getMessage());
        }

        return $this->redirectToRoute('employe_index');
    }

    // ── Export PDF ────────────────────────────────────────────────────

    #[Route('/pdf', name: 'employe_pdf', methods: ['GET'])]
    public function exportPdf(EmployeRepository $repo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $employes = $repo->findByAgriculteur($idAgriculteur);

        $pdf = new \TCPDF('L', 'mm', 'A4', true, 'UTF-8', false);
        $pdf->SetCreator('Ardhi');
        $pdf->SetAuthor('Ardhi');
        $pdf->SetTitle($this->translator->trans('employe.title'));
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->AddPage();

        $currentLocale = $this->translator->getLocale();
        $isAr = ($currentLocale === 'ar');
        $font = $isAr ? 'freeserif' : 'helvetica';

        if ($isAr) {
            $pdf->setRTL(true);
        }

        // Header green bar
        $pdf->SetFillColor(34, 120, 60);
        $pdf->Rect(0, 0, 297, 28, 'F');
        $pdf->SetFillColor(39, 174, 96);
        $pdf->Rect(0, 24, 297, 4, 'F');

        $pdf->SetFont($font, 'B', 18);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->SetY(5);
        $title = $this->translator->trans('employe.title');
        $pdf->Cell(0, 10, $isAr ? $title : strtoupper($title), 0, 1, 'C');
        
        $pdf->SetFont($font, '', 10);
        $date = (new \DateTime())->format('d/m/Y H:i');
        $total = count($employes);
        
        $genLabel = strtr($this->translator->trans('common.loading_time'), [
            'Chargé en %ms%ms' => 'Généré le',
            'Loaded in %ms%ms' => 'Generated on',
            'تم التحميل في %ms%ms' => 'تم الإنشاء في'
        ]);
        $totalLabel = $this->translator->trans('common.total');
        
        $pdf->Cell(0, 6, "$genLabel : $date | $totalLabel : $total", 0, 1, 'C');
        $pdf->Ln(8);

        // Table header
        $pdf->SetFont($font, 'B', 10);
        $pdf->SetFillColor(34, 120, 60);
        $pdf->SetTextColor(255, 255, 255);

        $pdf->Cell(15, 8, 'ID', 1, 0, 'C', true);
        $pdf->Cell(40, 8, $this->translator->trans('employe.col.nom'), 1, 0, 'C', true);
        $pdf->Cell(40, 8, $this->translator->trans('employe.col.prenom'), 1, 0, 'C', true);
        $pdf->Cell(60, 8, $this->translator->trans('employe.col.email'), 1, 0, 'C', true);
        $pdf->Cell(40, 8, $this->translator->trans('employe.col.poste'), 1, 0, 'C', true);
        $pdf->Cell(35, 8, $this->translator->trans('employe.col.telephone'), 1, 0, 'C', true);
        $pdf->Cell(20, 8, $this->translator->trans('employe.col.actif'), 1, 1, 'C', true);

        $pdf->SetFont($font, '', 9);
        $pdf->SetTextColor(0, 0, 0);

        foreach ($employes as $i => $emp) {
            $pdf->SetFillColor($i % 2 === 0 ? 245 : 255, $i % 2 === 0 ? 250 : 255, $i % 2 === 0 ? 245 : 255);

            $pdf->Cell(15, 7, $emp->getId(), 1, 0, 'C', true);
            $pdf->Cell(40, 7, $emp->getNom(), 1, 0, 'L', true);
            $pdf->Cell(40, 7, $emp->getPrenom(), 1, 0, 'L', true);
            $pdf->Cell(60, 7, $emp->getEmail(), 1, 0, 'L', true);
            $pdf->Cell(40, 7, $emp->getPoste() ?? '-', 1, 0, 'L', true);
            $pdf->Cell(35, 7, $emp->getTelephone() ?? '-', 1, 0, 'C', true);

            if ($emp->isActif()) {
                $pdf->SetFillColor(200, 240, 200);
                $pdf->Cell(20, 7, $this->translator->trans('common.active'), 1, 1, 'C', true);
            } else {
                $pdf->SetFillColor(255, 200, 200);
                $pdf->Cell(20, 7, $this->translator->trans('common.inactive'), 1, 1, 'C', true);
            }
        }

        // Footer
        $pdf->Ln(5);
        $pdf->SetFillColor(39, 174, 96);
        $pdf->Rect(10, $pdf->GetY(), 277, 1, 'F');
        $pdf->Ln(3);
        $pdf->SetFont('helvetica', 'I', 8);
        $pdf->SetTextColor(130, 130, 130);
        $pdf->Cell(0, 5, 'Ardhi - Gestion Agricole Intelligente | ' . $date, 0, 1, 'C');

        return new Response(
            $pdf->Output('employes_' . date('Ymd_His') . '.pdf', 'I'),
            200,
            ['Content-Type' => 'application/pdf']
        );
    }

    // ── Statistiques ──────────────────────────────────────────────────

    #[Route('/statistiques', name: 'employe_statistiques', methods: ['GET'])]
    public function statistiques(EmployeRepository $repo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $employes = $repo->findByAgriculteur($idAgriculteur);
        $total = count($employes);
        $actifData = $repo->countByActif($idAgriculteur);
        $posteData = $repo->countByPoste($idAgriculteur);

        return $this->render('EmployeTache/employe/statistiques.html.twig', [
            'total'            => $total,
            'actifData'        => $actifData,
            'posteData'        => $posteData,
            'supervision_mode' => $this->ctx->isSupervisionMode(),
            'nom_supervise'    => $this->ctx->getNomAgriculteurSupervise(),
        ]);
    }

    // ── Toggle actif ──────────────────────────────────────────────────

    #[Route('/{id}/toggle', name: 'employe_toggle', methods: ['POST'])]
    public function toggle(int $id, EntityManagerInterface $em, EmployeRepository $repo): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $employe = $repo->find($id);
        if ($employe && $employe->getIdAgriculteur() === $idAgriculteur) {
            $employe->setActif(!$employe->isActif());
            $em->flush();
            $statut = $employe->isActif() ? $this->translator->trans('common.active') : $this->translator->trans('common.inactive');
            $this->addFlash('success', $employe->getNomComplet() . ' : ' . $statut);
        }
        return $this->redirectToRoute('employe_index');
    }

    // ── QR Code Image ─────────────────────────────────────────────────

    #[Route('/{id<\d+>}/qr', name: 'employe_qr_image', methods: ['GET'])]
    public function qrImage(int $id, EmployeRepository $repo, QrCodeService $qrService): Response
    {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $employe = $repo->find($id);
        if (!$employe || $employe->getIdAgriculteur() !== $idAgriculteur) {
            throw $this->createNotFoundException('Employé introuvable.');
        }

        $qrUrl = $qrService->generateFicheUrl($employe->getId());
        $qrSvg = $qrService->generateQrCodeSvg($qrUrl, 150);

        return new Response($qrSvg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    // ── Fiche Mobile (Standalone HTML) ────────────────────────────────

    #[Route('/{id<\d+>}/fiche', name: 'employe_fiche', methods: ['GET'])]
    public function ficheMobile(int $id, EmployeRepository $repo): Response
    {
        // Attention : Cette route est destinée à être accédée via téléphone.
        // On ne fait pas de checkAccess strict de session si on veut permettre un scan depuis un wifi local sans login.
        // Mais pour la sécurité, on peut garder l'accès restreint, ou créer une route allégée.
        // 'Comme sur le desktop' : Le desktop démarrait un serveur sans authentification pour lire la fiche.
        
        $employe = $repo->find($id);
        if (!$employe) {
            throw $this->createNotFoundException('Employé introuvable.');
        }

        return $this->render('EmployeTache/employe/fiche_mobile.html.twig', [
            'employe' => $employe,
        ]);
    }

    // ── Fiche PDF Complet ─────────────────────────────────────────────

    #[Route('/{id<\d+>}/fiche-pdf', name: 'employe_fiche_pdf', methods: ['GET'])]
    public function exportFichePdf(
        int $id, 
        EmployeRepository $repo, 
        FicheEmployePdfService $fichePdfService,
        ParameterBagInterface $params
    ): Response {
        $result = $this->checkAccess();
        if ($result instanceof Response) return $result;
        $idAgriculteur = $result;

        $employe = $repo->find($id);
        if (!$employe || $employe->getIdAgriculteur() !== $idAgriculteur) {
            throw $this->createNotFoundException('Employé introuvable.');
        }

        $publicDir = $params->get('kernel.project_dir') . '/public';
        $pdfContent = $fichePdfService->genererFichePdf($employe, $publicDir);

        return new Response(
            $pdfContent,
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="fiche_' . $employe->getNom() . '_' . $employe->getPrenom() . '.pdf"'
            ]
        );
    }

    // ══════════════════════════════════════════════════════════════════
    // VALIDATION SERVEUR
    // ══════════════════════════════════════════════════════════════════

    private function validerDonnees(array $data, ValidatorInterface $validator,
                                    EmployeRepository $repo, ?int $excludeId = null): array
    {
        $errors = [];

        $v = $validator->validate($data['nom'], [
            new Assert\NotBlank(message: 'Le nom est obligatoire.'),
            new Assert\Length(min: 2, max: 100,
                minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
                maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'),
            new Assert\Regex(pattern: '/^[\p{L}\s\-\']+$/u',
                message: 'Le nom ne peut contenir que des lettres, espaces, tirets et apostrophes.'),
        ]);
        if (count($v)) $errors['nom'] = $v[0]->getMessage();

        $v = $validator->validate($data['prenom'], [
            new Assert\NotBlank(message: 'Le prénom est obligatoire.'),
            new Assert\Length(min: 2, max: 100,
                minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
                maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'),
            new Assert\Regex(pattern: '/^[\p{L}\s\-\']+$/u',
                message: 'Le prénom ne peut contenir que des lettres, espaces, tirets et apostrophes.'),
        ]);
        if (count($v)) $errors['prenom'] = $v[0]->getMessage();

        $v = $validator->validate($data['email'], [
            new Assert\NotBlank(message: "L'email est obligatoire."),
            new Assert\Email(message: "L'adresse email '{{ value }}' n'est pas valide."),
            new Assert\Length(max: 150, maxMessage: "L'email ne peut pas dépasser {{ limit }} caractères."),
        ]);
        if (count($v)) {
            $errors['email'] = $v[0]->getMessage();
        } elseif ($repo->emailExists($data['email'], $excludeId)) {
            $errors['email'] = 'Cet email est déjà utilisé par un autre employé.';
        }

        if ($data['poste'] !== null) {
            $v = $validator->validate($data['poste'], [
                new Assert\Length(max: 100, maxMessage: 'Le poste ne peut pas dépasser {{ limit }} caractères.')]);
            if (count($v)) $errors['poste'] = $v[0]->getMessage();
        }

        $v = $validator->validate($data['telephone'], [
            new Assert\NotBlank(message: 'Le numéro de téléphone est obligatoire pour les notifications urgentes.'),
            new Assert\Regex(pattern: '/^[0-9]{8}$/', message: 'Le téléphone doit contenir exactement 8 chiffres.'),
        ]);
        if (count($v)) $errors['telephone'] = $v[0]->getMessage();

        return $errors;
    }

    private function validerPhoto(mixed $photoFile): array
    {
        if (!$photoFile) return [];
        $v = \Symfony\Component\Validator\Validation::createValidator()->validate($photoFile, [
            new Assert\File(maxSize: '5M', maxSizeMessage: 'La photo ne doit pas dépasser 5 Mo.',
                mimeTypes: ['image/jpeg','image/png','image/webp'],
                mimeTypesMessage: 'La photo doit être au format JPG, PNG ou WebP.'),
        ]);
        return count($v) ? ['photo' => $v[0]->getMessage()] : [];
    }

    private function extractFormData(Request $r): array
    {
        return [
            'nom'       => trim($r->request->get('nom', '')),
            'prenom'    => trim($r->request->get('prenom', '')),
            'email'     => strtolower(trim($r->request->get('email', ''))),
            'poste'     => $r->request->get('poste') ? trim($r->request->get('poste')) : null,
            'telephone' => $r->request->get('telephone') ? trim($r->request->get('telephone')) : null,
            'actif'     => $r->request->get('actif') === '1',
        ];
    }

    private function hydraterEmploye(Employe $employe, array $data): void
    {
        $employe->setNom($data['nom']);
        $employe->setPrenom($data['prenom']);
        $employe->setEmail($data['email']);
        $employe->setPoste($data['poste']);
        $employe->setTelephone($data['telephone']);
        $employe->setActif($data['actif']);
    }

    private function uploadPhoto(\Symfony\Component\HttpFoundation\File\UploadedFile $file,
                                  int $idEmploye): ?string
    {
        try {
            $dir = $this->getParameter('kernel.project_dir') . '/public/uploads/employes/';
            if (!is_dir($dir)) mkdir($dir, 0777, true);
            $filename = 'EMP_' . $idEmploye . '_' . time() . '.' . $file->guessExtension();
            $file->move($dir, $filename);
            return '/uploads/employes/' . $filename;
        } catch (\Exception) {
            return null;
        }
    }
}