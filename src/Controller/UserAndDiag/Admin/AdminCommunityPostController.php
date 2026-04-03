<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityPost;
use App\Repository\UserAndDiag\CommunityPostRepository;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/community-posts')]
class AdminCommunityPostController extends AbstractController
{
    #[Route('', name: 'admin_community_post_index')]
    public function index(CommunityPostRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Posts Communauté',
            'icon' => 'bi-chat-square-text-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Titre', 'field' => 'title'],
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Likes', 'field' => 'likes'],
                ['label' => 'Dislikes', 'field' => 'dislikes'],
                ['label' => 'Résolu', 'field' => 'resolved', 'type' => 'bool'],
                ['label' => 'Créé le', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_community_post_new',
            'edit_route' => 'admin_community_post_edit',
            'delete_route' => 'admin_community_post_delete',
        ]);
    }

    #[Route('/new', name: 'admin_community_post_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new CommunityPost();
            $this->handle($request, $item, $em, $userRepo);
            $this->addFlash('success', 'Post créé.');
            return $this->redirectToRoute('admin_community_post_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouveau Post', 'fields' => $this->getFields($userRepo), 'cancel_route' => 'admin_community_post_index', 'csrf_token_id' => 'post_form']);
    }

    #[Route('/{id}/edit', name: 'admin_community_post_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, CommunityPostRepository $repo, UserRepository $userRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em, $userRepo);
            $this->addFlash('success', 'Post modifié.');
            return $this->redirectToRoute('admin_community_post_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Post #' . $item->getId(), 'fields' => $this->getFields($userRepo), 'item' => $item, 'cancel_route' => 'admin_community_post_index', 'csrf_token_id' => 'post_form']);
    }

    #[Route('/{id}/delete', name: 'admin_community_post_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, CommunityPostRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Post supprimé.');
        }
        return $this->redirectToRoute('admin_community_post_index');
    }

    private function handle(Request $r, CommunityPost $item, EntityManagerInterface $em, UserRepository $userRepo): void
    {
        $item->setTitle($r->request->get('title', ''));
        $item->setDescription($r->request->get('description') ?: null);
        $item->setImageUrl($r->request->get('image_url') ?: null);
        $item->setLikes((int) $r->request->get('likes', 0));
        $item->setDislikes((int) $r->request->get('dislikes', 0));
        $item->setIsResolved($r->request->has('is_resolved'));
        $item->setUser($r->request->get('user_id') ? $userRepo->find($r->request->get('user_id')) : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(UserRepository $userRepo): array
    {
        $users = array_map(fn($u) => ['id' => $u->getId(), 'label' => $u->getEmail()], $userRepo->findAll());
        return [
            ['name' => 'title', 'label' => 'Titre', 'getter' => 'title', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'getter' => 'description', 'type' => 'textarea'],
            ['name' => 'image_url', 'label' => 'Image URL', 'getter' => 'imageUrl'],
            ['name' => 'likes', 'label' => 'Likes', 'getter' => 'likes', 'type' => 'number', 'default' => '0'],
            ['name' => 'dislikes', 'label' => 'Dislikes', 'getter' => 'dislikes', 'type' => 'number', 'default' => '0'],
            ['name' => 'is_resolved', 'label' => 'Résolu', 'getter' => 'resolved', 'type' => 'checkbox'],
            ['name' => 'user_id', 'label' => 'Utilisateur', 'getter' => 'user', 'type' => 'relation_select', 'options' => $users, 'required' => true],
        ];
    }
}
