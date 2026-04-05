<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Abonnement;
use App\Form\UserAndDiag\Admin\AdminAbonnementType;
use App\Repository\UserAndDiag\AbonnementRepository;
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
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new Abonnement();
        $form = $this->createForm(AdminAbonnementType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Abonnement créé.');
            return $this->redirectToRoute('admin_abonnement_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvel Abonnement',
            'form' => $form,
            'cancel_route' => 'admin_abonnement_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_abonnement_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, AbonnementRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminAbonnementType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Abonnement modifié.');
            return $this->redirectToRoute('admin_abonnement_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Abonnement #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_abonnement_index',
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
}
