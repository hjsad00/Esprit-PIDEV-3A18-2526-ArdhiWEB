<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\TreatmentTask;
use App\Form\UserAndDiag\Admin\AdminTreatmentTaskType;
use App\Repository\UserAndDiag\TreatmentTaskRepository;
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
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new TreatmentTask();
        $form = $this->createForm(AdminTreatmentTaskType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Tâche créée.');
            return $this->redirectToRoute('admin_treatment_task_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvelle Tâche Traitement',
            'form' => $form,
            'cancel_route' => 'admin_treatment_task_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_treatment_task_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, TreatmentTaskRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminTreatmentTaskType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Tâche modifiée.');
            return $this->redirectToRoute('admin_treatment_task_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Tâche #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_treatment_task_index',
        ]);
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
}
