<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityPost;
use App\Form\UserAndDiag\Admin\AdminCommunityPostType;
use App\Repository\UserAndDiag\CommunityPostRepository;
use App\Service\UserAndDiag\ImgBBService;
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
                ['label' => 'Image', 'field' => 'imageUrl', 'type' => 'image'],
                ['label' => 'Titre', 'field' => 'title'],
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Likes', 'field' => 'likes'],
                ['label' => 'Résolu', 'field' => 'resolved', 'type' => 'bool'],
                ['label' => 'Créé le', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_community_post_new',
            'edit_route' => 'admin_community_post_edit',
            'delete_route' => 'admin_community_post_delete',
        ]);
    }

    #[Route('/new', name: 'admin_community_post_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ImgBBService $imgBBService): Response
    {
        $item = new CommunityPost();
        $form = $this->createForm(AdminCommunityPostType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $url = $imgBBService->uploadImage($imageFile);
                if ($url) {
                    $item->setImageUrl($url);
                } else {
                    $this->addFlash('danger', 'Échec de l\'upload de l\'image sur ImgBB.');
                }
            }
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Post créé.');
            return $this->redirectToRoute('admin_community_post_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Post',
            'form' => $form,
            'cancel_route' => 'admin_community_post_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_community_post_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, CommunityPostRepository $repo, ImgBBService $imgBBService): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminCommunityPostType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $url = $imgBBService->uploadImage($imageFile);
                if ($url) {
                    $item->setImageUrl($url);
                } else {
                    $this->addFlash('danger', 'Échec de l\'upload de l\'image sur ImgBB.');
                }
            }
            $em->flush();
            $this->addFlash('success', 'Post modifié.');
            return $this->redirectToRoute('admin_community_post_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Post #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_community_post_index',
        ]);
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
}
