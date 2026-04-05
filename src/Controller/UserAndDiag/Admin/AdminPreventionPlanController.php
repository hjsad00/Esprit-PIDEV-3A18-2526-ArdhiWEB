<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\PreventionPlan;
use App\Form\UserAndDiag\Admin\AdminPreventionPlanType;
use App\Repository\UserAndDiag\PreventionPlanRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/prevention-plans')]
class AdminPreventionPlanController extends AbstractController
{
    #[Route('', name: 'admin_prevention_plan_index')]
    public function index(PreventionPlanRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Plans Prévention',
            'icon' => 'bi-shield-fill-check',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Titre', 'field' => 'title'],
                ['label' => 'Impact', 'field' => 'impactLevel', 'type' => 'badge', 'color' => '#f39c12'],
                ['label' => 'Statut', 'field' => 'status', 'type' => 'badge', 'color' => '#198754'],
                ['label' => 'Durée (j)', 'field' => 'timelineDays'],
                ['label' => 'Coût', 'field' => 'estimatedCost'],
            ],
            'new_route' => 'admin_prevention_plan_new',
            'edit_route' => 'admin_prevention_plan_edit',
            'delete_route' => 'admin_prevention_plan_delete',
        ]);
    }

    #[Route('/new', name: 'admin_prevention_plan_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new PreventionPlan();
        $form = $this->createForm(AdminPreventionPlanType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Plan créé.');
            return $this->redirectToRoute('admin_prevention_plan_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Plan Prévention',
            'form' => $form,
            'cancel_route' => 'admin_prevention_plan_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_prevention_plan_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, PreventionPlanRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminPreventionPlanType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Plan modifié.');
            return $this->redirectToRoute('admin_prevention_plan_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Plan #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_prevention_plan_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_prevention_plan_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, PreventionPlanRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Plan supprimé.');
        }
        return $this->redirectToRoute('admin_prevention_plan_index');
    }
}
