<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityLike;
use App\Form\UserAndDiag\Admin\AdminCommunityLikeType;
use App\Repository\UserAndDiag\CommunityLikeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/community-likes')]
class AdminCommunityLikeController extends AbstractController
{
    #[Route('', name: 'admin_community_like_index')]
    public function index(CommunityLikeRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Likes',
            'icon' => 'bi-heart-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Post', 'field' => 'post', 'type' => 'relation'],
                ['label' => 'Comment', 'field' => 'comment', 'type' => 'relation'],
                ['label' => 'Type', 'field' => 'voteType', 'type' => 'badge', 'color' => '#dc3545'],
                ['label' => 'Créé le', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_community_like_new',
            'edit_route' => 'admin_community_like_edit',
            'delete_route' => 'admin_community_like_delete',
        ]);
    }

    #[Route('/new', name: 'admin_community_like_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new CommunityLike();
        $form = $this->createForm(AdminCommunityLikeType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Like créé.');
            return $this->redirectToRoute('admin_community_like_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Like',
            'form' => $form,
            'cancel_route' => 'admin_community_like_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_community_like_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, CommunityLikeRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminCommunityLikeType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Like modifié.');
            return $this->redirectToRoute('admin_community_like_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Like #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_community_like_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_community_like_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, CommunityLikeRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Like supprimé.');
        }
        return $this->redirectToRoute('admin_community_like_index');
    }
}
