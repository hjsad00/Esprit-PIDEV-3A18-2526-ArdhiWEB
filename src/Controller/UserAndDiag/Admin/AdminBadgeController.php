<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Badge;
use App\Repository\UserAndDiag\BadgeRepository;
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
                ['label' => 'Nom', 'field' => 'name'],
                ['label' => 'Description', 'field' => 'description', 'type' => 'truncate'],
                ['label' => 'Icône', 'field' => 'icon'],
                ['label' => 'Condition', 'field' => 'conditionType', 'type' => 'badge', 'color' => '#d63384'],
                ['label' => 'Seuil', 'field' => 'threshold'],
            ],
            'new_route' => 'admin_badge_new',
            'edit_route' => 'admin_badge_edit',
            'delete_route' => 'admin_badge_delete',
        ]);
    }

    #[Route('/new', name: 'admin_badge_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            $item = new Badge();
            $this->handle($request, $item, $em);
            $this->addFlash('success', 'Badge créé.');
            return $this->redirectToRoute('admin_badge_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouveau Badge', 'fields' => $this->getFields(), 'cancel_route' => 'admin_badge_index', 'csrf_token_id' => 'badge_form']);
    }

    #[Route('/{id}/edit', name: 'admin_badge_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, BadgeRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em);
            $this->addFlash('success', 'Badge modifié.');
            return $this->redirectToRoute('admin_badge_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Badge #' . $item->getId(), 'fields' => $this->getFields(), 'item' => $item, 'cancel_route' => 'admin_badge_index', 'csrf_token_id' => 'badge_form']);
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

    private function handle(Request $r, Badge $item, EntityManagerInterface $em): void
    {
        $item->setName($r->request->get('name', ''));
        $item->setDescription($r->request->get('description') ?: null);
        $item->setIcon($r->request->get('icon') ?: null);
        $item->setConditionType($r->request->get('condition_type') ?: 'DIAGNOSTIC');
        $item->setThreshold($r->request->get('threshold') ? (int) $r->request->get('threshold') : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(): array
    {
        return [
            ['name' => 'name', 'label' => 'Nom', 'getter' => 'name', 'required' => true],
            ['name' => 'description', 'label' => 'Description', 'getter' => 'description', 'type' => 'textarea'],
            ['name' => 'icon', 'label' => 'Icône', 'getter' => 'icon'],
            [
                'name' => 'condition_type',
                'label' => 'Type Condition',
                'getter' => 'conditionType',
                'type' => 'select',
                'options' => [
                    ['value' => 'DIAGNOSTIC', 'label' => 'Diagnostic'],
                    ['value' => 'POINTS', 'label' => 'Points'],
                    ['value' => 'HEALTHY_PLANTS', 'label' => 'Healthy Plants'],
                    ['value' => 'SOLUTION', 'label' => 'Solution'],
                ]
            ],
            ['name' => 'threshold', 'label' => 'Seuil', 'getter' => 'threshold', 'type' => 'number'],
        ];
    }
}
