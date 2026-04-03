<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityLike;
use App\Repository\UserAndDiag\CommunityLikeRepository;
use App\Repository\UserAndDiag\UserRepository;
use App\Repository\UserAndDiag\CommunityPostRepository;
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
                ['label' => 'Type', 'field' => 'voteType', 'type' => 'badge', 'color' => '#dc3545'],
                ['label' => 'Créé le', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'edit_route' => 'admin_community_like_edit',
            'delete_route' => 'admin_community_like_delete',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_community_like_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, CommunityLikeRepository $repo, UserRepository $userRepo, CommunityPostRepository $postRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $item->setVoteType($request->request->get('vote_type', 'LIKE'));
            $item->setUser($request->request->get('user_id') ? $userRepo->find($request->request->get('user_id')) : null);
            $item->setPost($request->request->get('post_id') ? $postRepo->find($request->request->get('post_id')) : null);
            $em->flush();
            $this->addFlash('success', 'Like modifié.');
            return $this->redirectToRoute('admin_community_like_index');
        }
        $users = array_map(fn($u) => ['id' => $u->getId(), 'label' => $u->getEmail()], $userRepo->findAll());
        $posts = array_map(fn($p) => ['id' => $p->getId(), 'label' => $p->getTitle()], $postRepo->findAll());
        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Like #' . $item->getId(),
            'item' => $item,
            'cancel_route' => 'admin_community_like_index',
            'csrf_token_id' => 'like_form',
            'fields' => [
                ['name' => 'user_id', 'label' => 'Utilisateur', 'getter' => 'user', 'type' => 'relation_select', 'options' => $users],
                ['name' => 'post_id', 'label' => 'Post', 'getter' => 'post', 'type' => 'relation_select', 'options' => $posts],
                ['name' => 'vote_type', 'label' => 'Vote', 'getter' => 'voteType', 'type' => 'select', 'options' => [['value' => 'LIKE', 'label' => 'Like'], ['value' => 'DISLIKE', 'label' => 'Dislike']]],
            ],
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
