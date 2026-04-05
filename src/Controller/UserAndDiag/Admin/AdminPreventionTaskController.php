<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\PreventionTask;
use App\Form\UserAndDiag\Admin\AdminPreventionTaskType;
use App\Repository\UserAndDiag\PreventionTaskRepository;
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
            ],
            'new_route' => 'admin_prevention_task_new',
            'edit_route' => 'admin_prevention_task_edit',
            'delete_route' => 'admin_prevention_task_delete',
        ]);
    }

    #[Route('/new', name: 'admin_prevention_task_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new PreventionTask();
        $form = $this->createForm(AdminPreventionTaskType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Tâche créée.');
            return $this->redirectToRoute('admin_prevention_task_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvelle Tâche Prévention',
            'form' => $form,
            'cancel_route' => 'admin_prevention_task_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_prevention_task_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, PreventionTaskRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminPreventionTaskType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Tâche modifiée.');
            return $this->redirectToRoute('admin_prevention_task_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Tâche #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_prevention_task_index',
        ]);
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
}
