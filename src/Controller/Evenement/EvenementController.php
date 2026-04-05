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
use App\Service\StatisticsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evenement')]
class EvenementController extends AbstractController
{
    // ─── LIST ────────────────────────────────────────────────────────────────
    #[Route('', name: 'app_evenement_index', methods: ['GET'])]
    public function index(
        Request $request,
        EvenementRepository $evenementRepo,
        EvenementFavorisRepository $favorisRepo
    ): Response {
        // Admin → redirect to admin dashboard
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_evenement_admin_dashboard');
        }

        $type    = $request->query->get('type');
        $statut  = $request->query->get('statut');
        $search  = $request->query->get('search');
        $evenements = $evenementRepo->findWithFilters($type, $statut, $search);

        $favorisIds = [];
        if ($this->getUser()) {
            foreach ($favorisRepo->findByUser($this->getUser()) as $fav) {
                $favorisIds[] = $fav->getEvenement()->getId();
            }
        }

        return $this->render('evenement/index.html.twig', [
            'evenements'  => $evenements,
            'favorisIds'  => $favorisIds,
            'type'        => $type,
            'statut'      => $statut,
            'search'      => $search,
        ]);
    }

    // ─── ADMIN DASHBOARD ─────────────────────────────────────────────────────
    #[Route('/admin-dashboard', name: 'app_evenement_admin_dashboard', methods: ['GET'])]
    public function adminDashboard(
        EvenementRepository $evenementRepo,
        StatisticsService $statisticsService
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return $this->render('evenement/admin_dashboard.html.twig', [
            'globalStats' => $statisticsService->getGlobalStatistics(),
            'evenements'  => $evenementRepo->findWithFilters(null, null, null),
        ]);
    }

    // ─── STATISTICS ──────────────────────────────────────────────────────────
    #[Route('/statistiques', name: 'app_evenement_statistics', methods: ['GET'])]
    public function statistics(StatisticsService $statisticsService): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
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

        return $this->render('evenement/statistics.html.twig', $data);
    }

    // ─── MES FAVORIS ─────────────────────────────────────────────────────────
    #[Route('/mes-favoris', name: 'app_evenement_favoris', methods: ['GET'])]
    public function mesFavoris(EvenementFavorisRepository $favorisRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        return $this->render('evenement/favoris.html.twig', [
            'favoris' => $favorisRepo->findByUser($this->getUser()),
        ]);
    }

    // ─── MES INSCRIPTIONS ────────────────────────────────────────────────────
    #[Route('/inscriptions', name: 'app_evenement_inscriptions', methods: ['GET'])]
    public function inscriptions(ParticipationRepository $participationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        return $this->render('evenement/inscriptions.html.twig', [
            'inscriptions' => $participationRepo->findByUserOrdered($this->getUser()),
        ]);
    }

    // ─── PARTICIPATIONS (admin/creator) ──────────────────────────────────────
    #[Route('/participations', name: 'app_evenement_participations', methods: ['GET'])]
    public function participations(ParticipationRepository $participationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($this->isGranted('ROLE_ADMIN')) {
            $participations = $participationRepo->findAllOrdered();
        } else {
            $participations = $participationRepo->findForCreator($this->getUser());
        }

        return $this->render('evenement/participations.html.twig', [
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
            return $this->redirectToRoute('app_evenement_participations');
        }

        $ev = $participation->getEvenement();
        if ($ev->getCreateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Non autorisé.');
            return $this->redirectToRoute('app_evenement_participations');
        }

        $participation->setStatut($statut);
        $em->flush();
        $this->addFlash('success', 'Statut mis à jour.');
        return $this->redirectToRoute('app_evenement_participations');
    }

    // ─── CREATE ──────────────────────────────────────────────────────────────
    #[Route('/nouveau', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($evenement->getDateFin() < $evenement->getDateDebut()) {
                $this->addFlash('danger', 'La date de fin doit être ≥ à la date de début.');
                return $this->render('evenement/new.html.twig', ['form' => $form, 'evenement' => $evenement]);
            }

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/evenements';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                $newFilename = uniqid('event_', true) . '.' . ($imageFile->guessExtension() ?: 'jpg');
                try {
                    $imageFile->move($uploadsDir, $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Impossible d\'enregistrer l\'image.');
                    return $this->render('evenement/new.html.twig', ['form' => $form, 'evenement' => $evenement]);
                }
                $evenement->setImageUrl('/uploads/evenements/' . $newFilename);
            }

            $evenement->setCreateur($this->getUser());
            $em->persist($evenement);
            $em->flush();
            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        return $this->render('evenement/new.html.twig', ['form' => $form, 'evenement' => $evenement]);
    }

    // ─── SHOW ────────────────────────────────────────────────────────────────
    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        Evenement $evenement,
        EvenementFavorisRepository $favorisRepo,
        ParticipationRepository $participationRepo
    ): Response {
        $isFavori = false;
        $userParticipation = null;
        $avisForm = null;

        if ($this->getUser()) {
            $isFavori          = (bool) $favorisRepo->findByUserAndEvenement($this->getUser(), $evenement);
            $userParticipation = $participationRepo->findByUserAndEvenement($this->getUser(), $evenement);

            // Show avis form only if user attended and event is finished and has no rating yet
            if ($userParticipation
                && $evenement->getStatut() === 'TERMINE'
                && $userParticipation->getStatut() === 'PRESENT'
                && $userParticipation->getNote() == 0
            ) {
                $avisForm = $this->createForm(AvisType::class, $userParticipation)->createView();
            }
        }

        // All reviews for this event
        $avis = $evenement->getParticipations()->filter(
            fn($p) => $p->getNote() > 0 && $p->getAvis()
        );

        return $this->render('evenement/show.html.twig', [
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

        if ($form->isSubmitted() && $form->isValid()) {
            if ($evenement->getDateFin() < $evenement->getDateDebut()) {
                $this->addFlash('danger', 'La date de fin doit être ≥ à la date de début.');
                return $this->render('evenement/edit.html.twig', ['form' => $form, 'evenement' => $evenement]);
            }

            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $uploadsDir = $this->getParameter('kernel.project_dir') . '/public/uploads/evenements';
                if (!is_dir($uploadsDir)) {
                    mkdir($uploadsDir, 0755, true);
                }
                $newFilename = uniqid('event_', true) . '.' . ($imageFile->guessExtension() ?: 'jpg');
                try {
                    $imageFile->move($uploadsDir, $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Impossible d\'enregistrer l\'image.');
                    return $this->render('evenement/edit.html.twig', ['form' => $form, 'evenement' => $evenement]);
                }
                $evenement->setImageUrl('/uploads/evenements/' . $newFilename);
            }

            $em->flush();
            $this->addFlash('success', 'Événement modifié avec succès !');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }

        return $this->render('evenement/edit.html.twig', ['form' => $form, 'evenement' => $evenement]);
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
        } else {
            $p = new Participation();
            $p->setEvenement($evenement)->setUtilisateur($this->getUser());
            $em->persist($p);
        }

        $em->flush();
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
}
