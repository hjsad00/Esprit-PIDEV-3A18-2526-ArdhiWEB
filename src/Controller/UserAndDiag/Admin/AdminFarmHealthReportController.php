<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\FarmHealthReport;
use App\Form\UserAndDiag\Admin\AdminFarmHealthReportType;
use App\Repository\UserAndDiag\FarmHealthReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/farm-health-reports')]
class AdminFarmHealthReportController extends AbstractController
{
    #[Route('', name: 'admin_farm_health_report_index')]
    public function index(FarmHealthReportRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Rapports Santé',
            'icon' => 'bi-file-earmark-medical-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Scan', 'field' => 'scan', 'type' => 'relation'],
                ['label' => 'Score', 'field' => 'healthScore'],
                ['label' => 'Biodiversité', 'field' => 'biodiversityScore'],
                ['label' => 'Généré le', 'field' => 'generatedAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_farm_health_report_new',
            'edit_route' => 'admin_farm_health_report_edit',
            'delete_route' => 'admin_farm_health_report_delete',
        ]);
    }

    #[Route('/new', name: 'admin_farm_health_report_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new FarmHealthReport();
        $form = $this->createForm(AdminFarmHealthReportType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Rapport créé.');
            return $this->redirectToRoute('admin_farm_health_report_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Rapport Santé',
            'form' => $form,
            'cancel_route' => 'admin_farm_health_report_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_farm_health_report_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, FarmHealthReportRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminFarmHealthReportType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Rapport modifié.');
            return $this->redirectToRoute('admin_farm_health_report_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Rapport #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_farm_health_report_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_farm_health_report_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, FarmHealthReportRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Rapport supprimé.');
        }
        return $this->redirectToRoute('admin_farm_health_report_index');
    }
}
