<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Abonnement;
use App\Repository\UserAndDiag\AbonnementRepository;
use App\Repository\UserAndDiag\UserRepository;
use App\Repository\UserAndDiag\OffreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/abonnements')]
class AdminAbonnementController extends AbstractController
{
    #[Route('', name: 'admin_abonnement_index')]
    public function index(AbonnementRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Abonnements',
            'icon' => 'bi-credit-card-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Type', 'field' => 'type'],
                ['label' => 'Prix', 'field' => 'prix'],
                ['label' => 'Début', 'field' => 'dateDebut', 'type' => 'date'],
                ['label' => 'Fin', 'field' => 'dateFin', 'type' => 'date'],
                ['label' => 'Statut', 'field' => 'statut', 'type' => 'badge', 'color' => '#198754'],
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
            ],
            'new_route' => 'admin_abonnement_new',
            'edit_route' => 'admin_abonnement_edit',
            'delete_route' => 'admin_abonnement_delete',
        ]);
    }

    #[Route('/new', name: 'admin_abonnement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepo, OffreRepository $offreRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new Abonnement();
            $this->handleForm($request, $item, $em, $userRepo, $offreRepo);
            $this->addFlash('success', 'Abonnement créé.');
            return $this->redirectToRoute('admin_abonnement_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvel Abonnement',
            'fields' => $this->getFields($userRepo, $offreRepo),
            'cancel_route' => 'admin_abonnement_index',
            'csrf_token_id' => 'abonnement_form',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_abonnement_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, AbonnementRepository $repo, UserRepository $userRepo, OffreRepository $offreRepo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }
        if ($request->isMethod('POST')) {
            $this->handleForm($request, $item, $em, $userRepo, $offreRepo);
            $this->addFlash('success', 'Abonnement modifié.');
            return $this->redirectToRoute('admin_abonnement_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Abonnement #' . $item->getId(),
            'fields' => $this->getFields($userRepo, $offreRepo),
            'item' => $item,
            'cancel_route' => 'admin_abonnement_index',
            'csrf_token_id' => 'abonnement_form',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_abonnement_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, AbonnementRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Abonnement supprimé.');
        }
        return $this->redirectToRoute('admin_abonnement_index');
    }

    private function handleForm(Request $r, Abonnement $item, EntityManagerInterface $em, UserRepository $userRepo, OffreRepository $offreRepo): void
    {
        $item->setType($r->request->get('type') ?: null);
        $item->setPrix((float) $r->request->get('prix', 0));
        $item->setStatut($r->request->get('statut') ?: 'ACTIF');
        if ($r->request->get('date_debut'))
            $item->setDateDebut(new \DateTime($r->request->get('date_debut')));
        if ($r->request->get('date_fin'))
            $item->setDateFin(new \DateTime($r->request->get('date_fin')));
        $item->setUser($r->request->get('user_id') ? $userRepo->find($r->request->get('user_id')) : null);
        $item->setOffre($r->request->get('offre_id') ? $offreRepo->find($r->request->get('offre_id')) : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(UserRepository $userRepo, OffreRepository $offreRepo): array
    {
        $users = array_map(fn($u) => ['id' => $u->getId(), 'label' => $u->getEmail()], $userRepo->findAll());
        $offres = array_map(fn($o) => ['id' => $o->getId(), 'label' => $o->getNom()], $offreRepo->findAll());
        return [
            ['name' => 'type', 'label' => 'Type', 'getter' => 'type'],
            ['name' => 'prix', 'label' => 'Prix', 'getter' => 'prix', 'type' => 'number', 'step' => '0.01', 'required' => true],
            [
                'name' => 'statut',
                'label' => 'Statut',
                'getter' => 'statut',
                'type' => 'select',
                'options' => [
                    ['value' => 'ACTIF', 'label' => 'Actif'],
                    ['value' => 'INACTIF', 'label' => 'Inactif'],
                    ['value' => 'EXPIRE', 'label' => 'Expiré'],
                ]
            ],
            ['name' => 'date_debut', 'label' => 'Date Début', 'getter' => 'dateDebut', 'type' => 'date'],
            ['name' => 'date_fin', 'label' => 'Date Fin', 'getter' => 'dateFin', 'type' => 'date'],
            ['name' => 'user_id', 'label' => 'Utilisateur', 'getter' => 'user', 'type' => 'relation_select', 'options' => $users],
            ['name' => 'offre_id', 'label' => 'Offre', 'getter' => 'offre', 'type' => 'relation_select', 'options' => $offres],
        ];
    }
}
