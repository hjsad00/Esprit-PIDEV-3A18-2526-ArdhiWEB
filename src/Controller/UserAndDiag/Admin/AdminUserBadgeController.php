<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\UserBadge;
use App\Form\UserAndDiag\Admin\AdminUserBadgeType;
use App\Repository\UserAndDiag\UserBadgeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/user-badges')]
class AdminUserBadgeController extends AbstractController
{
    #[Route('', name: 'admin_user_badge_index')]
    public function index(UserBadgeRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'User Badges',
            'icon' => 'bi-patch-check-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Badge', 'field' => 'badge', 'type' => 'relation', 'display' => 'name'],
                ['label' => 'Acquis le', 'field' => 'acquiredAt', 'type' => 'date'],
            ],
            'delete_route' => 'admin_user_badge_delete',
            'new_route' => 'admin_user_badge_new',
            'id_getter' => 'computedId',
        ]);
    }

    #[Route('/new', name: 'admin_user_badge_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new UserBadge();
        $form = $this->createForm(AdminUserBadgeType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'User Badge créé.');
            return $this->redirectToRoute('admin_user_badge_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau User Badge',
            'form' => $form,
            'cancel_route' => 'admin_user_badge_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_user_badge_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, UserBadgeRepository $repo): Response
    {
        $items = $repo->findAll();
        foreach ($items as $item) {
            $computedId = $item->getUser()->getId() * 10000 + $item->getBadge()->getId();
            if ($computedId == $id && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
                $em->remove($item);
                $em->flush();
                $this->addFlash('success', 'User Badge supprimé.');
                break;
            }
        }
        return $this->redirectToRoute('admin_user_badge_index');
    }
}
