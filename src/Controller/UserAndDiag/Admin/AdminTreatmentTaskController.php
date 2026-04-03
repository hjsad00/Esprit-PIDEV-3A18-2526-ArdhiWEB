<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\TreatmentTask;
use App\Repository\UserAndDiag\TreatmentTaskRepository;
use App\Repository\UserAndDiag\TreatmentPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/treatment-tasks')]
class AdminTreatmentTaskController extends AbstractController
{
    #[Route('', name: 'admin_treatment_task_index')]
    public function index(TreatmentTaskRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Tâches Traitement',
            'icon' => 'bi-check2-square',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Plan', 'field' => 'treatmentPlan', 'type' => 'relation'],
                ['label' => 'Jour', 'field' => 'dayOffset'],
                ['label' => 'Description', 'field' => 'taskDescription', 'type' => 'truncate'],
                ['label' => 'Statut', 'field' => 'status', 'type' => 'badge', 'color' => '#e67e22'],
            ],
            'new_route' => 'admin_treatment_task_new',
            'edit_route' => 'admin_treatment_task_edit',
            'delete_route' => 'admin_treatment_task_delete',
        ]);
    }

    #[Route('/new', name: 'admin_treatment_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, TreatmentPlanRepository $planRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new TreatmentTask();
            $this->handle($request, $item, $em, $planRepo);
            $this->addFlash('success', 'Tâche créée.');
            return $this->redirectToRoute('admin_treatment_task_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouvelle Tâche Traitement', 'fields' => $this->getFields($planRepo), 'cancel_route' => 'admin_treatment_task_index', 'csrf_token_id' => 'tt_form']);
    }

    #[Route('/{id}/edit', name: 'admin_treatment_task_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, TreatmentTaskRepository $repo, TreatmentPlanRepository $planRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em, $planRepo);
            $this->addFlash('success', 'Tâche modifiée.');
            return $this->redirectToRoute('admin_treatment_task_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Tâche #' . $item->getId(), 'fields' => $this->getFields($planRepo), 'item' => $item, 'cancel_route' => 'admin_treatment_task_index', 'csrf_token_id' => 'tt_form']);
    }

    #[Route('/{id}/delete', name: 'admin_treatment_task_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, TreatmentTaskRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Tâche supprimée.');
        }
        return $this->redirectToRoute('admin_treatment_task_index');
    }

    private function handle(Request $r, TreatmentTask $item, EntityManagerInterface $em, TreatmentPlanRepository $planRepo): void
    {
        $item->setDayOffset((int) $r->request->get('day_offset', 0));
        $item->setTaskDescription($r->request->get('task_description', ''));
        $item->setStatus($r->request->get('status') ?: 'PENDING');
        $item->setTechX($r->request->get('tech_x') ? (float) $r->request->get('tech_x') : 0);
        $item->setTechY($r->request->get('tech_y') ? (float) $r->request->get('tech_y') : 0);
        $item->setTreatmentPlan($r->request->get('treatment_plan_id') ? $planRepo->find($r->request->get('treatment_plan_id')) : null);
        $em->persist($item);
        $em->flush();
    }

    private function getFields(TreatmentPlanRepository $planRepo): array
    {
        $plans = array_map(fn($p) => ['id' => $p->getId(), 'label' => '#' . $p->getId()], $planRepo->findAll());
        return [
            ['name' => 'treatment_plan_id', 'label' => 'Plan', 'getter' => 'treatmentPlan', 'type' => 'relation_select', 'options' => $plans, 'required' => true],
            ['name' => 'day_offset', 'label' => 'Jour (offset)', 'getter' => 'dayOffset', 'type' => 'number', 'required' => true],
            ['name' => 'task_description', 'label' => 'Description', 'getter' => 'taskDescription', 'required' => true],
            ['name' => 'status', 'label' => 'Statut', 'getter' => 'status', 'type' => 'select', 'options' => [['value' => 'PENDING', 'label' => 'Pending'], ['value' => 'COMPLETED', 'label' => 'Completed'], ['value' => 'MISSED', 'label' => 'Missed']]],
            ['name' => 'tech_x', 'label' => 'Tech X', 'getter' => 'techX', 'type' => 'number', 'step' => '0.01', 'default' => '0'],
            ['name' => 'tech_y', 'label' => 'Tech Y', 'getter' => 'techY', 'type' => 'number', 'step' => '0.01', 'default' => '0'],
        ];
    }
}
