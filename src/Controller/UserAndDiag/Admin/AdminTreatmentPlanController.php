<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\TreatmentPlan;
use App\Form\UserAndDiag\Admin\AdminTreatmentPlanType;
use App\Repository\UserAndDiag\TreatmentPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/treatment-plans')]
class AdminTreatmentPlanController extends AbstractController
{
    #[Route('', name: 'admin_treatment_plan_index')]
    public function index(TreatmentPlanRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Plans Traitement',
            'icon' => 'bi-journal-medical',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Diagnostic', 'field' => 'diagnostic', 'type' => 'relation'],
                ['label' => 'Début', 'field' => 'startDate', 'type' => 'date'],
                ['label' => 'Statut', 'field' => 'status', 'type' => 'badge', 'color' => '#9b59b6'],
            ],
            'new_route' => 'admin_treatment_plan_new',
            'edit_route' => 'admin_treatment_plan_edit',
            'delete_route' => 'admin_treatment_plan_delete',
        ]);
    }

    #[Route('/new', name: 'admin_treatment_plan_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new TreatmentPlan();
        $form = $this->createForm(AdminTreatmentPlanType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Plan créé.');
            return $this->redirectToRoute('admin_treatment_plan_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Plan Traitement',
            'form' => $form,
            'cancel_route' => 'admin_treatment_plan_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_treatment_plan_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, TreatmentPlanRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminTreatmentPlanType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Plan modifié.');
            return $this->redirectToRoute('admin_treatment_plan_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Plan #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_treatment_plan_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_treatment_plan_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, TreatmentPlanRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Plan supprimé.');
        }
        return $this->redirectToRoute('admin_treatment_plan_index');
    }
}
