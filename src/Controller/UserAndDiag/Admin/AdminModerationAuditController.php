<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\ModerationAudit;
use App\Form\UserAndDiag\Admin\AdminModerationAuditType;
use App\Repository\UserAndDiag\ModerationAuditRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/moderation-audits')]
class AdminModerationAuditController extends AbstractController
{
    #[Route('', name: 'admin_moderation_audit_index')]
    public function index(ModerationAuditRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Audit de Modération',
            'icon' => 'bi-shield-shaded',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Modérateur', 'field' => 'moderator', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Cible', 'field' => 'targetUser', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Action', 'field' => 'action'],
                ['label' => 'Date', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_moderation_audit_new',
            'edit_route' => 'admin_moderation_audit_edit',
            'delete_route' => 'admin_moderation_audit_delete',
        ]);
    }

    #[Route('/new', name: 'admin_moderation_audit_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new ModerationAudit();
        $form = $this->createForm(AdminModerationAuditType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Audit enregistré.');
            return $this->redirectToRoute('admin_moderation_audit_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvel Audit',
            'form' => $form->createView(),
            'cancel_route' => 'admin_moderation_audit_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_moderation_audit_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, ModerationAuditRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();

        $form = $this->createForm(AdminModerationAuditType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Audit modifié.');
            return $this->redirectToRoute('admin_moderation_audit_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Audit #' . $item->getId(),
            'form' => $form->createView(),
            'cancel_route' => 'admin_moderation_audit_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_moderation_audit_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, ModerationAuditRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Audit supprimé.');
        }
        return $this->redirectToRoute('admin_moderation_audit_index');
    }
}
