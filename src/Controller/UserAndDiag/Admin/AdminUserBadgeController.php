<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\UserBadge;
use App\Repository\UserAndDiag\UserBadgeRepository;
use App\Repository\UserAndDiag\UserRepository;
use App\Repository\UserAndDiag\BadgeRepository;
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
    public function new(Request $request, EntityManagerInterface $em, UserRepository $userRepo, BadgeRepository $badgeRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new UserBadge();
            $item->setUser($request->request->get('user_id') ? $userRepo->find($request->request->get('user_id')) : null);
            $item->setBadge($request->request->get('badge_id') ? $badgeRepo->find($request->request->get('badge_id')) : null);
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'User Badge créé.');
            return $this->redirectToRoute('admin_user_badge_index');
        }
        $users = array_map(fn($u) => ['id' => $u->getId(), 'label' => $u->getEmail()], $userRepo->findAll());
        $badges = array_map(fn($b) => ['id' => $b->getId(), 'label' => $b->getName()], $badgeRepo->findAll());
        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau User Badge',
            'cancel_route' => 'admin_user_badge_index',
            'csrf_token_id' => 'ub_form',
            'fields' => [
                ['name' => 'user_id', 'label' => 'Utilisateur', 'getter' => 'user', 'type' => 'relation_select', 'options' => $users, 'required' => true],
                ['name' => 'badge_id', 'label' => 'Badge', 'getter' => 'badge', 'type' => 'relation_select', 'options' => $badges, 'required' => true],
            ],
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_user_badge_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, UserBadgeRepository $repo, UserRepository $userRepo, BadgeRepository $badgeRepo): Response
    {
        // UserBadge has composite key (user_id, badge_id), but we use a query approach
        // Since the list template uses item.id which won't work for composite keys, we handle it differently
        $items = $repo->findAll();
        foreach ($items as $item) {
            // We use a hash-based approach: id = user_id * 10000 + badge_id  
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
