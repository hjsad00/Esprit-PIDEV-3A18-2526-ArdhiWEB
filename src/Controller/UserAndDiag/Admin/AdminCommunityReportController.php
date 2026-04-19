<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\CommunityReport;
use App\Form\UserAndDiag\Admin\AdminCommunityReportType;
use App\Repository\UserAndDiag\CommunityReportRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/community-reports')]
class AdminCommunityReportController extends AbstractController
{
    #[Route('', name: 'admin_community_report_index')]
    public function index(CommunityReportRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Signalements Communauté',
            'icon' => 'bi-flag-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Reporter', 'field' => 'reporter', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Post', 'field' => 'post', 'type' => 'relation', 'display' => 'title'],
                ['label' => 'Comment', 'field' => 'comment', 'type' => 'relation', 'display' => 'id'],
                ['label' => 'Raison', 'field' => 'reason'],
                ['label' => 'Résolu', 'field' => 'resolved', 'type' => 'bool'],
                ['label' => 'Date', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_community_report_new',
            'edit_route' => 'admin_community_report_edit',
            'delete_route' => 'admin_community_report_delete',
        ]);
    }

    #[Route('/new', name: 'admin_community_report_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new CommunityReport();
        $form = $this->createForm(AdminCommunityReportType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Signalement ajouté.');
            return $this->redirectToRoute('admin_community_report_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Signalement',
            'form' => $form->createView(),
            'cancel_route' => 'admin_community_report_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_community_report_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, CommunityReportRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();

        $form = $this->createForm(AdminCommunityReportType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Signalement modifié.');
            return $this->redirectToRoute('admin_community_report_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Signalement #' . $item->getId(),
            'form' => $form->createView(),
            'cancel_route' => 'admin_community_report_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_community_report_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, CommunityReportRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Signalement supprimé.');
        }
        return $this->redirectToRoute('admin_community_report_index');
    }
}
