<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityComment;
use App\Repository\UserAndDiag\CommunityCommentRepository;
use App\Repository\UserAndDiag\CommunityPostRepository;
use App\Repository\UserAndDiag\UserRepository;
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
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepo, CommunityPostRepository $postRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new CommunityComment();
            $this->handle($request, $item, $em, $userRepo, $postRepo);
            $this->addFlash('success', 'Commentaire créé.');
            return $this->redirectToRoute('admin_community_comment_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouveau Commentaire', 'fields' => $this->getFields($userRepo, $postRepo), 'cancel_route' => 'admin_community_comment_index', 'csrf_token_id' => 'comment_form']);
    }

    #[Route('/{id}/edit', name: 'admin_community_comment_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, CommunityCommentRepository $repo, UserRepository $userRepo, CommunityPostRepository $postRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em, $userRepo, $postRepo);
            $this->addFlash('success', 'Commentaire modifié.');
            return $this->redirectToRoute('admin_community_comment_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Commentaire #' . $item->getId(), 'fields' => $this->getFields($userRepo, $postRepo), 'item' => $item, 'cancel_route' => 'admin_community_comment_index', 'csrf_token_id' => 'comment_form']);
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

    private function handle(Request $r, CommunityComment $item, EntityManagerInterface $em, UserRepository $userRepo, CommunityPostRepository $postRepo): void
    {
        $item->setContent($r->request->get('content', ''));
        $item->setLikes((int) $r->request->get('likes', 0));
        $item->setDislikes((int) $r->request->get('dislikes', 0));
        $item->setIsSolution($r->request->has('is_solution'));
        $item->setUser($r->request->get('user_id') ? $userRepo->find($r->request->get('user_id')) : null);
        $item->setPost($r->request->get('post_id') ? $postRepo->find($r->request->get('post_id')) : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(UserRepository $userRepo, CommunityPostRepository $postRepo): array
    {
        $users = array_map(fn($u) => ['id' => $u->getId(), 'label' => $u->getEmail()], $userRepo->findAll());
        $posts = array_map(fn($p) => ['id' => $p->getId(), 'label' => $p->getTitle()], $postRepo->findAll());
        return [
            ['name' => 'post_id', 'label' => 'Post', 'getter' => 'post', 'type' => 'relation_select', 'options' => $posts, 'required' => true],
            ['name' => 'user_id', 'label' => 'Utilisateur', 'getter' => 'user', 'type' => 'relation_select', 'options' => $users, 'required' => true],
            ['name' => 'content', 'label' => 'Contenu', 'getter' => 'content', 'type' => 'textarea', 'required' => true],
            ['name' => 'likes', 'label' => 'Likes', 'getter' => 'likes', 'type' => 'number', 'default' => '0'],
            ['name' => 'dislikes', 'label' => 'Dislikes', 'getter' => 'dislikes', 'type' => 'number', 'default' => '0'],
            ['name' => 'is_solution', 'label' => 'Solution', 'getter' => 'solution', 'type' => 'checkbox'],
        ];
    }
}
