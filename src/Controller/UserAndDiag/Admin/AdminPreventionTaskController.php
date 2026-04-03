<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\PreventionTask;
use App\Repository\UserAndDiag\PreventionTaskRepository;
use App\Repository\UserAndDiag\PreventionPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/prevention-tasks')]
class AdminPreventionTaskController extends AbstractController
{
    #[Route('', name: 'admin_prevention_task_index')]
    public function index(PreventionTaskRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Tâches Prévention',
            'icon' => 'bi-list-check',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Plan', 'field' => 'preventionPlan', 'type' => 'relation'],
                ['label' => 'Jour', 'field' => 'dayOffset'],
                ['label' => 'Description', 'field' => 'taskDescription', 'type' => 'truncate'],
                ['label' => 'Statut', 'field' => 'status', 'type' => 'badge', 'color' => '#1abc9c'],
                ['label' => 'Complété le', 'field' => 'completedAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_prevention_task_new',
            'edit_route' => 'admin_prevention_task_edit',
            'delete_route' => 'admin_prevention_task_delete',
        ]);
    }

    #[Route('/new', name: 'admin_prevention_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, PreventionPlanRepository $planRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new PreventionTask();
            $this->handle($request, $item, $em, $planRepo);
            $this->addFlash('success', 'Tâche créée.');
            return $this->redirectToRoute('admin_prevention_task_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouvelle Tâche Prévention', 'fields' => $this->getFields($planRepo), 'cancel_route' => 'admin_prevention_task_index', 'csrf_token_id' => 'pt_form']);
    }

    #[Route('/{id}/edit', name: 'admin_prevention_task_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, PreventionTaskRepository $repo, PreventionPlanRepository $planRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em, $planRepo);
            $this->addFlash('success', 'Tâche modifiée.');
            return $this->redirectToRoute('admin_prevention_task_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Tâche #' . $item->getId(), 'fields' => $this->getFields($planRepo), 'item' => $item, 'cancel_route' => 'admin_prevention_task_index', 'csrf_token_id' => 'pt_form']);
    }

    #[Route('/{id}/delete', name: 'admin_prevention_task_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, PreventionTaskRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Tâche supprimée.');
        }
        return $this->redirectToRoute('admin_prevention_task_index');
    }

    private function handle(Request $r, PreventionTask $item, EntityManagerInterface $em, PreventionPlanRepository $planRepo): void
    {
        $item->setDayOffset((int) $r->request->get('day_offset', 0));
        $item->setTaskDescription($r->request->get('task_description', ''));
        $item->setStatus($r->request->get('status') ?: 'PENDING');
        $item->setProofPhotoUrl($r->request->get('proof_photo_url') ?: null);
        $item->setPreventionPlan($r->request->get('prevention_plan_id') ? $planRepo->find($r->request->get('prevention_plan_id')) : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(PreventionPlanRepository $planRepo): array
    {
        $plans = array_map(fn($p) => ['id' => $p->getId(), 'label' => '#' . $p->getId() . ' - ' . $p->getTitle()], $planRepo->findAll());
        return [
            ['name' => 'prevention_plan_id', 'label' => 'Plan', 'getter' => 'preventionPlan', 'type' => 'relation_select', 'options' => $plans, 'required' => true],
            ['name' => 'day_offset', 'label' => 'Jour (offset)', 'getter' => 'dayOffset', 'type' => 'number', 'required' => true],
            ['name' => 'task_description', 'label' => 'Description', 'getter' => 'taskDescription', 'required' => true],
            ['name' => 'status', 'label' => 'Statut', 'getter' => 'status', 'type' => 'select', 'options' => [['value' => 'PENDING', 'label' => 'Pending'], ['value' => 'COMPLETED', 'label' => 'Completed'], ['value' => 'MISSED', 'label' => 'Missed']]],
            ['name' => 'proof_photo_url', 'label' => 'Photo Preuve URL', 'getter' => 'proofPhotoUrl'],
        ];
    }
}
