<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Badge;
use App\Form\UserAndDiag\Admin\AdminBadgeType;
use App\Repository\UserAndDiag\BadgeRepository;
use App\Service\UserAndDiag\ImgBBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/badges')]
class AdminBadgeController extends AbstractController
{
    #[Route('', name: 'admin_badge_index')]
    public function index(BadgeRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Badges',
            'icon' => 'bi-award-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Icône', 'field' => 'icon', 'type' => 'image'],
                ['label' => 'Nom', 'field' => 'name'],
                ['label' => 'Description', 'field' => 'description', 'type' => 'truncate'],
                ['label' => 'Condition', 'field' => 'conditionType', 'type' => 'badge', 'color' => '#d63384'],
                ['label' => 'Seuil', 'field' => 'threshold'],
            ],
            'new_route' => 'admin_badge_new',
            'edit_route' => 'admin_badge_edit',
            'delete_route' => 'admin_badge_delete',
        ]);
    }

    #[Route('/new', name: 'admin_badge_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ImgBBService $imgBBService): Response
    {
        $item = new Badge();
        $form = $this->createForm(AdminBadgeType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $iconFile = $form->get('iconFile')->getData();
            if ($iconFile) {
                $url = $imgBBService->uploadImage($iconFile);
                if ($url) {
                    $item->setIcon($url);
                } else {
                    $this->addFlash('danger', 'Échec de l\'upload de l\'icône sur ImgBB.');
                }
            }
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Badge créé.');
            return $this->redirectToRoute('admin_badge_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Badge',
            'form' => $form,
            'cancel_route' => 'admin_badge_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_badge_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, BadgeRepository $repo, ImgBBService $imgBBService): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminBadgeType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $iconFile = $form->get('iconFile')->getData();
            if ($iconFile) {
                $url = $imgBBService->uploadImage($iconFile);
                if ($url) {
                    $item->setIcon($url);
                } else {
                    $this->addFlash('danger', 'Échec de l\'upload de l\'icône sur ImgBB.');
                }
            }
            $em->flush();
            $this->addFlash('success', 'Badge modifié.');
            return $this->redirectToRoute('admin_badge_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Badge #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_badge_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_badge_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, BadgeRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Badge supprimé.');
        }
        return $this->redirectToRoute('admin_badge_index');
    }
}
