<?php

namespace App\Controller\Evenement;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\EvenementFavoris;
use App\Entity\Evenement\Participation;
use App\Form\Evenement\EvenementType;
use App\Repository\Evenement\EvenementRepository;
use App\Repository\Evenement\EvenementFavorisRepository;
use App\Repository\Evenement\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evenement')]
class EvenementController extends AbstractController
{
    #[Route('', name: 'app_evenement_index', methods: ['GET'])]
    public function index(Request $request, EvenementRepository $evenementRepo, EvenementFavorisRepository $favorisRepo): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_evenement_admin_dashboard');
        }

        $type = $request->query->get('type');
        $statut = $request->query->get('statut');
        $search = $request->query->get('search');
        $evenements = $evenementRepo->findWithFilters($type, $statut, $search);
        $favorisIds = [];
        if ($this->getUser()) {
            foreach ($favorisRepo->findByUser($this->getUser()) as $fav) {
                $favorisIds[] = $fav->getEvenement()->getId();
            }
        }
        return $this->render('evenement/index.html.twig', ['evenements' => $evenements, 'favorisIds' => $favorisIds, 'type' => $type, 'statut' => $statut, 'search' => $search]);
    }

    #[Route('/admin-dashboard', name: 'app_evenement_admin_dashboard', methods: ['GET'])]
    public function adminDashboard(EvenementRepository $evenementRepo, \App\Service\StatisticsService $statisticsService): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $globalStats = $statisticsService->getGlobalStatistics();
        $evenements = $evenementRepo->findWithFilters(null, null, null);

        return $this->render('evenement/admin_dashboard.html.twig', [
            'globalStats' => $globalStats,
            'evenements' => $evenements,
        ]);
    }

    #[Route('/nouveau', name: 'app_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $evenement = new Evenement();
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $evenement->setCreateur($this->getUser());
            $em->persist($evenement);
            $em->flush();
            $this->addFlash('success', '�v�nement cr�� avec succ�s !');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        return $this->render('evenement/new.html.twig', ['form' => $form, 'evenement' => $evenement]);
    }

    #[Route('/mes-favoris', name: 'app_evenement_favoris', methods: ['GET'])]
    public function mesFavoris(EvenementFavorisRepository $favorisRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $favoris = $favorisRepo->findByUser($this->getUser());
        return $this->render('evenement/favoris.html.twig', ['favoris' => $favoris]);
    }

    #[Route('/{id}', name: 'app_evenement_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Evenement $evenement, EvenementFavorisRepository $favorisRepo, ParticipationRepository $participationRepo): Response
    {
        $isFavori = false;
        $userParticipation = null;
        if ($this->getUser()) {
            $isFavori = (bool) $favorisRepo->findByUserAndEvenement($this->getUser(), $evenement);
            $userParticipation = $participationRepo->findByUserAndEvenement($this->getUser(), $evenement);
        }
        return $this->render('evenement/show.html.twig', ['evenement' => $evenement, 'isFavori' => $isFavori, 'userParticipation' => $userParticipation]);
    }

    #[Route('/participations', name: 'app_evenement_participations', methods: ['GET'])]
    public function participations(ParticipationRepository $participationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        if ($this->isGranted('ROLE_ADMIN')) {
            $participations = $participationRepo->findAllOrdered();
            $isAdmin = true;
        } else {
            $participations = $participationRepo->findForCreator($this->getUser());
            $isAdmin = false;
        }

        return $this->render('evenement/participations.html.twig', [
            'participations' => $participations,
            'isAdmin' => $isAdmin,
        ]);
    }

    #[Route('/inscriptions', name: 'app_evenement_inscriptions', methods: ['GET'])]
    public function inscriptions(ParticipationRepository $participationRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $inscriptions = $participationRepo->findByUserOrdered($this->getUser());
        return $this->render('evenement/inscriptions.html.twig', ['inscriptions' => $inscriptions]);
    }

    #[Route('/participation/{id}/statut', name: 'app_evenement_participation_update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function updateParticipationStatus(Participation $participation, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $allowedStatuses = ['CONFIRME', 'EN_ATTENTE', 'REFUSE', 'ANNULE'];
        $statut = $request->request->get('statut');
        if (!in_array($statut, $allowedStatuses, true)) {
            $this->addFlash('danger', 'Statut invalide.');
            return $this->redirectToRoute('app_evenement_participations');
        }

        $evenement = $participation->getEvenement();
        if ($evenement->getCreateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Vous n\'êtes pas autorisé à gérer cette participation.');
            return $this->redirectToRoute('app_evenement_participations');
        }

        $participation->setStatut($statut);
        if ($statut === 'CONFIRME') {
            $participation->setDateInscription(new \DateTime());
        }
        $em->flush();

        $this->addFlash('success', 'Statut de participation mis à jour.');
        return $this->redirectToRoute('app_evenement_participations');
    }

    #[Route('/{id}/modifier', name: 'app_evenement_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($evenement->getCreateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Vous n\'�tes pas autoris� � modifier cet �v�nement.');
            return $this->redirectToRoute('app_evenement_index');
        }
        $form = $this->createForm(EvenementType::class, $evenement);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', '�v�nement modifi� avec succ�s !');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        return $this->render('evenement/edit.html.twig', ['form' => $form, 'evenement' => $evenement]);
    }

    #[Route('/{id}/supprimer', name: 'app_evenement_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Evenement $evenement, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($evenement->getCreateur() !== $this->getUser() && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('danger', 'Vous n\'�tes pas autoris� � supprimer cet �v�nement.');
            return $this->redirectToRoute('app_evenement_index');
        }
        if ($this->isCsrfTokenValid('delete' . $evenement->getId(), $request->request->get('_token'))) {
            $em->remove($evenement);
            $em->flush();
            $this->addFlash('success', '�v�nement supprim� avec succ�s.');
        }
        return $this->redirectToRoute('app_evenement_index');
    }

    #[Route('/{id}/favori', name: 'app_evenement_toggle_favori', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function toggleFavori(Evenement $evenement, EvenementFavorisRepository $favorisRepo, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if (!$this->isCsrfTokenValid('favori' . $evenement->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        $existing = $favorisRepo->findByUserAndEvenement($this->getUser(), $evenement);
        if ($existing) {
            $em->remove($existing);
            $this->addFlash('info', 'Retir� des favoris.');
        } else {
            $favori = new EvenementFavoris();
            $favori->setEvenement($evenement);
            $favori->setUtilisateur($this->getUser());
            $em->persist($favori);
            $this->addFlash('success', 'Ajout� aux favoris !');
        }
        $em->flush();
        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    #[Route('/{id}/inscrire', name: 'app_evenement_inscrire', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function inscrire(Evenement $evenement, ParticipationRepository $participationRepo, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if (!$this->isCsrfTokenValid('inscrire' . $evenement->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        $existing = $participationRepo->findByUserAndEvenement($this->getUser(), $evenement);
        if ($existing && $existing->getStatut() !== 'ANNULE') {
            $this->addFlash('warning', 'Vous �tes d�j� inscrit � cet �v�nement.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        if ($evenement->getPlacesRestantes() <= 0) {
            $this->addFlash('danger', 'D�sol�, il n\'y a plus de places disponibles.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        if (in_array($evenement->getStatut(), ['TERMINE', 'ANNULE'])) {
            $this->addFlash('danger', 'Cet �v�nement n\'accepte plus d\'inscriptions.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        if ($existing && $existing->getStatut() === 'ANNULE') {
            $existing->setStatut('CONFIRME');
            $existing->setDateInscription(new \DateTime());
        } else {
            $participation = new Participation();
            $participation->setEvenement($evenement);
            $participation->setUtilisateur($this->getUser());
            $em->persist($participation);
        }
        $em->flush();
        $this->addFlash('success', 'Inscription confirm�e ! Bienvenue � l\'�v�nement.');
        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    #[Route('/{id}/desinscrire', name: 'app_evenement_desinscrire', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function desinscrire(Evenement $evenement, ParticipationRepository $participationRepo, EntityManagerInterface $em, Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if (!$this->isCsrfTokenValid('desinscrire' . $evenement->getId(), $request->request->get('_token'))) {
            $this->addFlash('danger', 'Token invalide.');
            return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
        }
        $participation = $participationRepo->findByUserAndEvenement($this->getUser(), $evenement);
        if ($participation) {
            $participation->setStatut('ANNULE');
            $em->flush();
            $this->addFlash('info', 'Votre inscription a �t� annul�e.');
        }
        return $this->redirectToRoute('app_evenement_show', ['id' => $evenement->getId()]);
    }

    #[Route('/statistiques', name: 'app_evenement_statistics', methods: ['GET'])]
    public function statistics(\App\Service\StatisticsService $statisticsService): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();
        $data = [];
        $role = 'CLIENT';

        if ($this->isGranted('ROLE_ADMIN')) {
            $role = 'ADMIN';
            $data['globalStats'] = $statisticsService->getGlobalStatistics();
            $data['topRatedEvents'] = $statisticsService->getTopRatedEvents();
        } elseif ($this->isGranted('ROLE_AGRICULTEUR')) {
            $role = 'AGRICULTEUR';
            $data['userStats'] = $statisticsService->getUserStatistics($user);
            $data['creatorStats'] = $statisticsService->getCreatorStatistics($user);
        } else {
            $role = $user->getRole() ?: 'CLIENT';
            $data['userStats'] = $statisticsService->getUserStatistics($user);
        }
        $data['role'] = $role;

        return $this->render('evenement/statistics.html.twig', $data);
    }
}
