<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityComment;
use App\Form\UserAndDiag\Admin\AdminCommunityCommentType;
use App\Repository\UserAndDiag\CommunityCommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/community-comments')]
class AdminCommunityCommentController extends AbstractController
{
    #[Route('', name: 'admin_community_comment_index')]
    public function index(CommunityCommentRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Commentaires',
            'icon' => 'bi-chat-dots-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Post', 'field' => 'post', 'type' => 'relation'],
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Contenu', 'field' => 'content', 'type' => 'truncate'],
                ['label' => 'Likes', 'field' => 'likes'],
                ['label' => 'Solution', 'field' => 'solution', 'type' => 'bool'],
                ['label' => 'Créé le', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_community_comment_new',
            'edit_route' => 'admin_community_comment_edit',
            'delete_route' => 'admin_community_comment_delete',
        ]);
    }

    #[Route('/new', name: 'admin_community_comment_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new CommunityComment();
        $form = $this->createForm(AdminCommunityCommentType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Commentaire créé.');
            return $this->redirectToRoute('admin_community_comment_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Commentaire',
            'form' => $form,
            'cancel_route' => 'admin_community_comment_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_community_comment_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, CommunityCommentRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminCommunityCommentType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Commentaire modifié.');
            return $this->redirectToRoute('admin_community_comment_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Commentaire #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_community_comment_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_community_comment_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, CommunityCommentRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }
        return $this->redirectToRoute('admin_community_comment_index');
    }
}
