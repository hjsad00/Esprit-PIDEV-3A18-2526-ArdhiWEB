<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Review;
use App\Form\UserAndDiag\Admin\AdminReviewType;
use App\Repository\UserAndDiag\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/reviews')]
class AdminReviewController extends AbstractController
{
    #[Route('', name: 'admin_review_index')]
    public function index(ReviewRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Reviews',
            'icon' => 'bi-clipboard-check-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Type', 'field' => 'reviewType', 'type' => 'badge', 'color' => '#6610f2'],
                ['label' => 'Statut', 'field' => 'status', 'type' => 'badge', 'color' => '#198754'],
                ['label' => 'Expert', 'field' => 'expert', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Diagnostic', 'field' => 'diagnostic', 'type' => 'relation'],
                ['label' => 'Créé le', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_review_new',
            'edit_route' => 'admin_review_edit',
            'delete_route' => 'admin_review_delete',
        ]);
    }

    #[Route('/new', name: 'admin_review_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new Review();
        $form = $this->createForm(AdminReviewType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Review créée.');
            return $this->redirectToRoute('admin_review_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvelle Review',
            'form' => $form,
            'cancel_route' => 'admin_review_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_review_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, ReviewRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminReviewType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Review modifiée.');
            return $this->redirectToRoute('admin_review_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Review #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_review_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_review_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, ReviewRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Review supprimée.');
        }
        return $this->redirectToRoute('admin_review_index');
    }
}
