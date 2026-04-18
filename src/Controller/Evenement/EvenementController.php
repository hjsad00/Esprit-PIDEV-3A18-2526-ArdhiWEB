<?php

namespace App\Controller\Evenement;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\EvenementFavoris;
use App\Entity\Evenement\Participation;
use App\Form\Evenement\EvenementType;
use App\Form\Evenement\AvisType;
use App\Repository\Evenement\EvenementRepository;
use App\Repository\Evenement\EvenementFavorisRepository;
use App\Repository\Evenement\ParticipationRepository;
use App\Service\Evenement\EvenementParticipationMailer;
use App\Service\Evenement\EvenementStatusSyncService;
use App\Service\Evenement\GeminiAIEventService;
use App\Service\Evenement\ParticipationPredictionService;
use App\Service\Evenement\StatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evenement')]
class EvenementController extends AbstractController
{
    public function __construct(
        private EvenementStatusSyncService   $eventStatusSync,
        private EvenementParticipationMailer $participationMailer,
        private GeminiAIEventService         $aiService,
        private ParticipationPredictionService $predictionService,
        private LoggerInterface              $logger
    ) {}

    // ─── LIST ────────────────────────────────────────────────────────────────
    #[Route('', name: 'app_evenement_index', methods: ['GET'])]
    public function index(
        Request $request,
        EvenementRepository $evenementRepo,
        EvenementFavorisRepository $favorisRepo
    ): Response {
        $this->eventStatusSync->syncAll();

        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_evenement_admin_dashboard');
        }

        $type       = $request->query->get('type');
        $statut     = $request->query->get('statut');
        $search     = $request->query->get('search');
        $evenements = $evenementRepo->findWithFilters($type, $statut, $search);

        $favorisIds = [];
        if ($this->getUser()) {
            foreach ($favorisRepo->findByUser($this->getUser()) as $fav) {
                $favorisIds[] = $fav->getEvenement()->getId();
            }
        }

        return $this->render('evenement/index.html.twig', [
            'evenements' => $evenements,
            'favorisIds' => $favorisIds,
            'type'       => $type,
            'statut'     => $statut,
            'search'     => $search,
        ]);
    }

    // ─── FILTER (AJAX) ────────────────────────────────────────────────────
    #[Route('/filter', name: 'app_evenement_filter', methods: ['GET'])]
    public function filter(
        Request $request,
        EvenementRepository $evenementRepo,
        EvenementFavorisRepository $favorisRepo
    ): Response {
        // Check for AJAX request
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest';

        $type       = $request->query->get('type');
        $statut     = $request->query->get('statut');
        $search     = $request->query->get('search');
        $evenements = $evenementRepo->findWithFilters($type, $statut, $search);

        $favorisIds = [];
        if ($this->getUser()) {
            foreach ($favorisRepo->findByUser($this->getUser()) as $fav) {
                $favorisIds[] = $fav->getEvenement()->getId();
            }
        }

        // Return partial template for AJAX requests
        if ($isAjax) {
            return $this->render('evenement/_filter_results.html.twig', [
                'evenements' => $evenements,
                'favorisIds' => $favorisIds,
                'type'       => $type,
                'statut'     => $statut,
                'search'     => $search,
            ]);
        }

        // Fallback to full page if not AJAX
        return $this->redirectToRoute('app_evenement_index', [
            'type'   => $type,
            'statut' => $statut,
            'search' => $search,
        ]);
    }
    #[Route('/admin-dashboard', name: 'app_evenement_admin_dashboard', methods: ['GET'])]
    public function adminDashboard(
        EvenementRepository $evenementRepo,
        StatisticsService $statisticsService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->eventStatusSync->syncAll();

        $gs = $statisticsService->getGlobalStatistics();

        $byStatus = [];
        foreach ($gs['eventsByStatus'] as $row) {
            $byStatus[$row['statut']] = $row['count'];
        }

        $stats = [
            ['label' => 'Événements',    'count' => $gs['totalEvents'],         'icon' => 'bi-calendar-event-fill', 'color' => '#667A3F', 'route' => 'app_evenement_admin_dashboard'],
            ['label' => 'Participations','count' => $gs['totalParticipations'], 'icon' => 'bi-people-fill',         'color' => '#3498DB', 'route' => 'app_evenement_participations'],
            ['label' => 'À venir',       'count' => $byStatus['A_VENIR']  ?? 0, 'icon' => 'bi-hourglass-split',    'color' => '#8BC34A', 'route' => 'app_evenement_admin_dashboard'],
            ['label' => 'En cours',      'count' => $byStatus['EN_COURS'] ?? 0, 'icon' => 'bi-play-circle-fill',   'color' => '#3498DB', 'route' => 'app_evenement_admin_dashboard'],
            ['label' => 'Terminés',      'count' => $byStatus['TERMINE']  ?? 0, 'icon' => 'bi-check-circle-fill',  'color' => '#95a5a6', 'route' => 'app_evenement_admin_dashboard'],
            ['label' => 'Annulés',       'count' => $byStatus['ANNULE']   ?? 0, 'icon' => 'bi-x-circle-fill',      'color' => '#E74C3C', 'route' => 'app_evenement_admin_dashboard'],
            ['label' => 'Note moyenne',  'count' => $gs['avgRating'],            'icon' => 'bi-star-fill',           'color' => '#F39C12', 'route' => 'app_evenement_statistics'],
            ['label' => 'Taux présence', 'count' => $gs['tauxPresence'].'%',    'icon' => 'bi-person-check-fill',  'color' => '#1abc9c', 'route' => 'app_evenement_participations'],
        ];

        return $this->render('evenement/admin_dashboard.html.twig', [
            'stats'      => $stats,
            'evenements' => $evenementRepo->findWithFilters(null, null, null),
        ]);
    }

    // ─── ADMIN LISTE ─────────────────────────────────────────────────────────
    #[Route('/admin-liste', name: 'app_evenement_admin_liste', methods: ['GET'])]
    public function adminListe(EvenementRepository $evenementRepo): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $this->eventStatusSync->syncAll();
        return $this->render('evenement/admin_liste.html.twig', [
            'evenements' => $evenementRepo->findWithFilters(null, null, null),
        ]);
    }

    // ─── STATISTICS ──────────────────────────────────────────────────────────
    #[Route('/statistiques', name: 'app_evenement_statistics', methods: ['GET'])]
    public function statistics(StatisticsService $statisticsService): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->eventStatusSync->syncAll();
        $user = $this->getUser();
        $data = ['role' => 'CLIENT'];

        if ($this->isGranted('ROLE_ADMIN')) {
            $data['role']           = 'ADMIN';
            $data['globalStats']    = $statisticsService->getGlobalStatistics();
            $data['topRatedEvents'] = $statisticsService->getGlobalStatistics()['topRatedEvents'];
        } elseif ($this->isGranted('ROLE_AGRICULTEUR')) {
            $data['role']          = 'AGRICULTEUR';
            $data['userStats']     = $statisticsService->getUserStatistics($user);
            $data['creatorStats']  = $statisticsService->getCreatorStatistics($user);
        } else {
            $data['userStats'] = $statisticsService->getUserStatistics($user);
        }

        $template = $this->isGranted('ROLE_ADMIN')
            ? 'evenement/admin_statistiques.html.twig'
            : 'evenement/statistics.html.twig';

        return $this->render($template, $data);
    }

    #[Route('/calendrier', name: 'app_evenement_calendrier', methods: ['GET'])]
    public function calendrier(EvenementRepository $evenementRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->eventStatusSync->syncAll();

        $events         = $evenementRepo->findWithFilters(null, null, null);
        $calendarEvents = array_map(static function (Evenement $e): array {
            return [
                'id'        => $e->getId(),
                'titre'     => $e->getTitre(),
                'type'      => $e->getType(),
                'statut'    => $e->getStatut(),
                'lieu'      => $e->getLieu(),
                'dateDebut' => $e->getDateDebut()?->format('Y-m-d'),
                'dateFin'   => $e->getDateFin()?->format('Y-m-d'),
            ];
        }, $events);

        $template = $this->isGranted('ROLE_ADMIN')
            ? 'evenement/admin_calendrier.html.twig'
            : 'evenement/calendrier.html.twig';

        return $this->render($template, ['calendarEvents' => $calendarEvents]);
    }

    // ─── MES FAVORIS ─────────────────────────────────────────────────────────
    #[Route('/mes-favoris', name: 'app_evenement_favoris', methods: ['GET'])]
    public function mesFavoris(EvenementFavorisRepository $favorisRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->eventStatusSync->syncAll();
        return $this->render('evenement/favoris.html.twig', [
            'favoris' => $favorisRepo->findByUser($this->getUser()),
        ]);
    }

    // ─── MES INSCRIPTIONS ────────────────────────────────────────────────────
    #[Route('/inscriptions', name: 'app_evenement_inscriptions', methods: ['GET'])]
    public function inscriptions(ParticipationRepository $participationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->eventStatusSync->syncAll();
        return $this->render('evenement/inscriptions.html.twig', [
            'inscriptions' => $participationRepo->findByUserOrdered($this->getUser()),
        ]);
    }

    // ─── MES ÉVÉNEMENTS ──────────────────────────────────────────────────────
    #[Route('/mes-evenements', name: 'app_evenement_mes', methods: ['GET'])]
    public function mesEvenements(Request $request, EvenementRepository $evenementRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->eventStatusSync->syncAll();

        if ($this->getUser()->getRole() !== 'AGRICULTEUR') {
            return $this->redirectToRoute('app_evenement_index');
        }

        $type   = $request->query->get('type');
        $statut = $request->query->get('statut');
        $search = $request->query->get('search');

        $evenements = $evenementRepo->findByCreateurWithFilters($this->getUser(), $type, $statut, $search);

        return $this->render('evenement/mes_evenements.html.twig', [
            'evenements' => $evenements,
            'type'       => $type,
            'statut'     => $statut,
            'search'     => $search,
        ]);
    }

    // ─── PARTICIPATIONS (admin/creator) ──────────────────────────────────────
    #[Route('/participations', name: 'app_evenement_participations', methods: ['GET'])]
    public function participations(ParticipationRepository $participationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->eventStatusSync->syncAll();

        $participations = $this->isGranted('ROLE_ADMIN')
            ? $participationRepo->findAllOrdered()
            : $participationRepo->findForCreator($this->getUser());

        $template = $this->isGranted('ROLE_ADMIN')
            ? 'evenement/admin_participations.html.twig'
            : 'evenement/participations.html.twig';

        return $this->render($template, [
            'participations' => $participations,
            'isAdmin'        => $this->isGranted('ROLE_ADMIN'),
        ]);
    }

    // ─── UPDATE PARTICIPATION STATUS ─────────────────────────────────────────
    #[Route('/participation/{id}/statut', name: 'app_evenement_participation_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateParticipationStatus(
        Participation $participation,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $statut = $request->request->get('statut');
        if (!in_array($statut, ['CONFIRME', 'EN_ATTENTE', 'REFUSE', 'ANNULE', 'PRESENT'], true)) {
            $this->addFlash('danger', 'Statut invalide.');
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_evenement_participations'));
        }

        $ev = $participation->getEvenement();
        if ($ev->getCreateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Non autorisé.');
            return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_evenement_participations'));
        }

        $participation->setStatut($statut);

        if (
            $statut === 'PRESENT'
            && $ev->getStatut() === 'TERMINE'
            && !$participation->isAttestationEnvoyee()
        ) {
            try {
                $this->participationMailer->sendPresenceCertificateAndReviewInvite($participation);
                $participation->setAttestationEnvoyee(true);
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Statut mis à jour, mais envoi de l\'attestation impossible.');
            }
        }

        $em->flush();
        $this->addFlash('success', 'Statut mis à jour.');
        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_evenement_participations'));
    }

    // ─── CREATE ──────────────────────────────────────────────────────────────
    #[Route('/nouveau', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $user */
        $user         = $this->getUser();
        $organisateur = trim(($user->getNom() ?? '') . ' ' . ($user->getPrenom() ?? ''));
        if ($organisateur === '') {
            $organisateur = $user->getEmail();
        }

        $evenement = new Evenement();
        $evenement->setOrganisateur($organisateur);
        $evenement->setCreateur($user);

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // ── Handle AI-generated image ──
            $aiImagePath = $request->request->get('ai_image_path');
            if ($aiImagePath && empty($form->get('imageFile')->getData())) {
                $evenement->setImageUrl($aiImagePath);
            }

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $uploadsDir  = $this->getParameter('kernel.project_dir') . '/public/uploads/evenements';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                $newFilename = uniqid('event_', true) . '.' . ($imageFile->guessExtension() ?: 'jpg');
                try {
                    $imageFile->move($uploadsDir, $newFilename);
                    $evenement->setImageUrl('/uploads/evenements/' . $newFilename);
                } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException $e) {
                    $this->addFlash('danger', "Impossible d'enregistrer l'image.");
                    $tpl = $this->isGranted('ROLE_ADMIN') ? 'evenement/admin_new.html.twig' : 'evenement/new.html.twig';
                    return $this->render($tpl, ['form' => $form, 'evenement' => $evenement]);
                }
            }

            $em->persist($evenement);
            $em->flush();

            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $tpl = $this->isGranted('ROLE_ADMIN') ? 'evenement/admin_new.html.twig' : 'evenement/new.html.twig';
        return $this->render($tpl, ['form' => $form, 'evenement' => $evenement]);
    }

    // ─── EDIT ────────────────────────────────────────────────────────────────
    #[Route('/{id}/modifier', name: 'app_evenement_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($evenement->getCreateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Non autorisé.');
            return $this->redirectToRoute('app_evenement_index');
        }

        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            // ── Handle AI-generated image ──
            $aiImagePath = $request->request->get('ai_image_path');
            if ($aiImagePath && empty($form->get('imageFile')->getData())) {
                $evenement->setImageUrl($aiImagePath);
            }

            /** @var \App\Entity\UserAndDiag\User $creator */
            $creator = $evenement->getCreateur();
            if ($creator) {
                $organisateur = trim(($creator->getNom() ?? '') . ' ' . ($creator->getPrenom() ?? ''));
                $evenement->setOrganisateur($organisateur ?: $creator->getEmail());
            }

            if ($form->isValid()) {
                $imageFile = $form->get('imageFile')->getData();
                if ($imageFile) {
                    $uploadsDir  = $this->getParameter('kernel.project_dir') . '/public/uploads/evenements';
                    if (!is_dir($uploadsDir)) {
                        mkdir($uploadsDir, 0755, true);
                    }
                    $newFilename = uniqid('event_', true) . '.' . ($imageFile->guessExtension() ?: 'jpg');
                    try {
                        $imageFile->move($uploadsDir, $newFilename);
                        $evenement->setImageUrl('/uploads/evenements/' . $newFilename);
                    } catch (\Symfony\Component\HttpFoundation\File\Exception\FileException $e) {
                        $this->addFlash('danger', "Impossible d'enregistrer l'image.");
                        $tpl = $this->isGranted('ROLE_ADMIN') ? 'evenement/admin_edit.html.twig' : 'evenement/edit.html.twig';
                        return $this->render($tpl, ['form' => $form, 'evenement' => $evenement]);
                    }
                }

                $em->flush();
                $this->addFlash('success', 'Événement modifié avec succès !');
                return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
            }
        }

        $tpl = $this->isGranted('ROLE_ADMIN') ? 'evenement/admin_edit.html.twig' : 'evenement/edit.html.twig';
        return $this->render($tpl, ['form' => $form, 'evenement' => $evenement]);
    }

    // ─── SHOW ────────────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        EvenementRepository $evenementRepo,
        EvenementFavorisRepository $favorisRepo,
        ParticipationRepository $participationRepo
    ): Response {
        $evenement = $evenementRepo->find($id);

        if (!$evenement) {
            $this->addFlash('warning', "L'événement demandé est introuvable.");
            return $this->redirectToRoute('app_evenement_index');
        }
        $this->eventStatusSync->syncOne($evenement);

        $isFavori          = false;
        $userParticipation = null;
        $avisForm          = null;

        if ($this->getUser()) {
            $isFavori          = (bool) $favorisRepo->findByUserAndEvenement($this->getUser(), $evenement);
            $userParticipation = $participationRepo->findByUserAndEvenement($this->getUser(), $evenement);

            if (
                $userParticipation
                && $evenement->getStatut() === 'TERMINE'
                && $userParticipation->getStatut() === 'PRESENT'
                && $userParticipation->getNote() == 0
            ) {
                $avisForm = $this->createForm(AvisType::class, $userParticipation)->createView();
            }
        }

        $avis = $evenement->getParticipations()->filter(fn($p) => $p->getNote() > 0);

        $template = $this->isGranted('ROLE_ADMIN')
            ? 'evenement/admin_show.html.twig'
            : 'evenement/show.html.twig';

        return $this->render($template, [
            'evenement'         => $evenement,
            'isFavori'          => $isFavori,
            'userParticipation' => $userParticipation,
            'avisForm'          => $avisForm,
            'avis'              => $avis,
        ]);
    }

    // ─── AJOUTER AVIS ────────────────────────────────────────────────────────
    #[Route('/{id}/avis', name: 'app_evenement_avis', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function ajouterAvis(
        Evenement $evenement,
        ParticipationRepository $participationRepo,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->eventStatusSync->syncOne($evenement);

        $participation = $participationRepo->findByUserAndEvenement($this->getUser(), $evenement);

        if (!$participation || $participation->getStatut() !== 'PRESENT') {
            $this->addFlash('danger', 'Vous devez avoir assisté à cet événement pour laisser un avis.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        if ($evenement->getStatut() !== 'TERMINE') {
            $this->addFlash('danger', "L'événement n'est pas encore terminé.");
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $form = $this->createForm(AvisType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Votre avis a été enregistré, merci !');
        } else {
            $this->addFlash('danger', 'Veuillez sélectionner une note.');
        }

        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────
    #[Route('/{id}/supprimer', name: 'app_evenement_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($evenement->getCreateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Non autorisé.');
            return $this->redirectToRoute('app_evenement_index');
        }

        if ($this->isCsrfTokenValid('delete' . $evenement->getId(), $request->request->get('_token'))) {
            $em->remove($evenement);
            $em->flush();
            $this->addFlash('success', 'Événement supprimé.');
        }

        return $this->redirectToRoute('app_evenement_index');
    }

    // ─── TOGGLE FAVORI ───────────────────────────────────────────────────────
    #[Route('/{id}/favori', name: 'app_evenement_toggle_favori', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleFavori(
        Evenement $evenement,
        EvenementFavorisRepository $favorisRepo,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->eventStatusSync->syncOne($evenement);

        if (!$this->isCsrfTokenValid('favori' . $evenement->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $existing = $favorisRepo->findByUserAndEvenement($this->getUser(), $evenement);
        if ($existing) {
            $em->remove($existing);
            $this->addFlash('info', 'Retiré des favoris.');
        } else {
            $fav = new EvenementFavoris();
            $fav->setEvenement($evenement)->setUtilisateur($this->getUser());
            $em->persist($fav);
            $this->addFlash('success', 'Ajouté aux favoris !');
        }

        $em->flush();
        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    // ─── INSCRIRE ────────────────────────────────────────────────────────────
    #[Route('/{id}/inscrire', name: 'app_evenement_inscrire', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function inscrire(
        Evenement $evenement,
        ParticipationRepository $participationRepo,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->eventStatusSync->syncOne($evenement);

        if (!$this->isCsrfTokenValid('inscrire' . $evenement->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $existing = $participationRepo->findByUserAndEvenement($this->getUser(), $evenement);

        if ($existing && $existing->getStatut() !== 'ANNULE') {
            $this->addFlash('warning', 'Vous êtes déjà inscrit.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        if ($evenement->getPlacesRestantes() <= 0) {
            $this->addFlash('danger', "Plus de places disponibles.");
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        if (in_array($evenement->getStatut(), ['TERMINE', 'ANNULE'])) {
            $this->addFlash('danger', "Inscriptions fermées.");
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        if ($existing && $existing->getStatut() === 'ANNULE') {
            $existing->setStatut('CONFIRME');
            $existing->setDateInscription(new \DateTime());
            $existing->setRappelJ3Envoye(false);
            $existing->setRappelJ1Envoye(false);
        } else {
            $p = new Participation();
            $p->setEvenement($evenement)->setUtilisateur($this->getUser());
            $em->persist($p);
            $existing = $p;
        }

        $em->flush();

        try {
            $this->participationMailer->sendInscriptionConfirmation($existing);
        } catch (\Throwable $e) {
            $this->logger->error('Confirmation email failed for participation #{id}: {message}', [
                'id'      => $existing->getId(),
                'message' => $e->getMessage(),
            ]);
            $this->addFlash('warning', 'Inscription validée, mais email de confirmation non envoyé.');
        }

        $this->addFlash('success', 'Inscription confirmée !');
        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    // ─── DÉSINSCRIRE ─────────────────────────────────────────────────────────
    #[Route('/{id}/desinscrire', name: 'app_evenement_desinscrire', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function desinscrire(
        Evenement $evenement,
        ParticipationRepository $participationRepo,
        EntityManagerInterface $em,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $this->eventStatusSync->syncOne($evenement);

        if (!$this->isCsrfTokenValid('desinscrire' . $evenement->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        $p = $participationRepo->findByUserAndEvenement($this->getUser(), $evenement);
        if ($p) {
            $p->setStatut('ANNULE');
            $em->flush();
            $this->addFlash('info', 'Inscription annulée.');
        }

        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    // ═══════════════════════════════════════════════════════════════════════════
    // ─── AI FEATURES (new — ported from Java desktop app) ─────────────────────
    // ═══════════════════════════════════════════════════════════════════════════

    /**
     * AJAX — Generate AI description + Unsplash image for a new event.
     *
     * POST /evenement/ai/generer
     * Body (JSON): { "titre": "...", "type": "FOIRE", "lieu": "Tunis" }
     * Response (JSON): { "description": "...", "imagePath": "/uploads/evenements/xxx.jpg" }
     */
    #[Route('/ai/generer', name: 'app_evenement_ai_generate', methods: ['POST'])]
    public function aiGenerate(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $data  = json_decode($request->getContent(), true) ?? [];
        $titre = trim($data['titre'] ?? '');
        $type  = trim($data['type']  ?? '');
        $lieu  = trim($data['lieu']  ?? '');

        if ($titre === '' || $type === '' || $lieu === '') {
            return $this->json(['error' => 'Titre, type et lieu sont requis.'], 400);
        }

        $result = $this->aiService->genererEvenementComplet($titre, $type, $lieu);

        return $this->json([
            'description' => $result['description'],
            'imagePath'   => $result['imagePath'],
        ]);
    }

    /**
     * AJAX — Predict attendance for a (not-yet-saved) event.
     *
     * POST /evenement/ai/predire
     * Body (JSON): { "titre": "...", "type": "FOIRE", "lieu": "Tunis",
     *                "dateDebut": "2025-10-01", "nombrePlacesMax": 200,
     *                "description": "..." }
     * Response (JSON): { "participantsPredits": 120, "confiance": 0.75,
     *                    "confianceTexte": "Élevée", "facteurs": {...},
     *                    "recommandations": {...} }
     */
    #[Route('/ai/predire', name: 'app_evenement_ai_predict', methods: ['POST'])]
    public function aiPredict(Request $request): JsonResponse
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $data = json_decode($request->getContent(), true) ?? [];

        // Build a temporary (non-persisted) Evenement for the prediction engine
        $tempEvent = new Evenement();
        $tempEvent->setTitre($data['titre']       ?? '');
        $tempEvent->setType($data['type']          ?? 'FOIRE');
        $tempEvent->setLieu($data['lieu']          ?? '');
        $tempEvent->setDescription($data['description'] ?? '');
        $tempEvent->setNombrePlacesMax((int) ($data['nombrePlacesMax'] ?? 100));

        if (!empty($data['dateDebut'])) {
            try {
                $tempEvent->setDateDebut(new \DateTime($data['dateDebut']));
            } catch (\Throwable) {
                // leave null — scorer handles it
            }
        }

        $result = $this->predictionService->predireParticipation($tempEvent);

        return $this->json($result);
    }
}
