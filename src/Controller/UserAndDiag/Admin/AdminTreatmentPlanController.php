<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\TreatmentPlan;
use App\Repository\UserAndDiag\TreatmentPlanRepository;
use App\Repository\UserAndDiag\DiagnosticRepository;
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
                ['label' => 'Statut', 'field' => 'status', 'type' => 'badge', 'color' => '#9b59b6'],
                ['label' => 'Date Début', 'field' => 'startDate', 'type' => 'date'],
            ],
            'new_route' => 'admin_treatment_plan_new',
            'edit_route' => 'admin_treatment_plan_edit',
            'delete_route' => 'admin_treatment_plan_delete',
        ]);
    }

    #[Route('/new', name: 'admin_treatment_plan_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, DiagnosticRepository $diagRepo): Response
    {
        if ($request->isMethod('POST')) {
            $item = new TreatmentPlan();
            $this->handle($request, $item, $em, $diagRepo);
            $this->addFlash('success', 'Plan créé.');
            return $this->redirectToRoute('admin_treatment_plan_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Nouveau Plan Traitement', 'fields' => $this->getFields($diagRepo), 'cancel_route' => 'admin_treatment_plan_index', 'csrf_token_id' => 'tp_form']);
    }

    #[Route('/{id}/edit', name: 'admin_treatment_plan_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, TreatmentPlanRepository $repo, DiagnosticRepository $diagRepo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();
        if ($request->isMethod('POST')) {
            $this->handle($request, $item, $em, $diagRepo);
            $this->addFlash('success', 'Plan modifié.');
            return $this->redirectToRoute('admin_treatment_plan_index');
        }
        return $this->render('UserAndDiag/admin/crud/form.html.twig', ['page_title' => 'Modifier Plan #' . $item->getId(), 'fields' => $this->getFields($diagRepo), 'item' => $item, 'cancel_route' => 'admin_treatment_plan_index', 'csrf_token_id' => 'tp_form']);
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

    private function handle(Request $r, TreatmentPlan $item, EntityManagerInterface $em, DiagnosticRepository $diagRepo): void
    {
        $item->setDiagnostic($r->request->get('diagnostic_id') ? $diagRepo->find($r->request->get('diagnostic_id')) : null);
        $item->setStatus($r->request->get('status') ?: 'ACTIVE');
        if ($r->request->get('start_date'))
            $item->setStartDate(new \DateTime($r->request->get('start_date')));
        $em->persist($item);
        $em->flush();
    }

    private function getFields(DiagnosticRepository $diagRepo): array
    {
        $diags = array_map(fn($d) => ['id' => $d->getId(), 'label' => '#' . $d->getId()], $diagRepo->findAll());
        return [
            ['name' => 'diagnostic_id', 'label' => 'Diagnostic', 'getter' => 'diagnostic', 'type' => 'relation_select', 'options' => $diags, 'required' => true],
            ['name' => 'status', 'label' => 'Statut', 'getter' => 'status', 'type' => 'select', 'options' => [['value' => 'ACTIVE', 'label' => 'Active'], ['value' => 'COMPLETED', 'label' => 'Completed'], ['value' => 'ABANDONED', 'label' => 'Abandoned']]],
            ['name' => 'start_date', 'label' => 'Date Début', 'getter' => 'startDate', 'type' => 'datetime'],
        ];
    }
}
