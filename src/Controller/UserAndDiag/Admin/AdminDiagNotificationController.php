<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\DiagNotification;
use App\Form\UserAndDiag\Admin\AdminDiagNotificationType;
use App\Repository\UserAndDiag\DiagNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/diag-notifications')]
class AdminDiagNotificationController extends AbstractController
{
    #[Route('', name: 'admin_diag_notification_index')]
    public function index(DiagNotificationRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Notifications',
            'icon' => 'bi-bell-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Utilisateur', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Type', 'field' => 'type'],
                ['label' => 'Message', 'field' => 'message'],
                ['label' => 'Lu', 'field' => 'isRead', 'type' => 'bool'],
                ['label' => 'Date', 'field' => 'createdAt', 'type' => 'date'],
            ],
            'new_route' => 'admin_diag_notification_new',
            'edit_route' => 'admin_diag_notification_edit',
            'delete_route' => 'admin_diag_notification_delete',
        ]);
    }

    #[Route('/new', name: 'admin_diag_notification_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new DiagNotification();
        $form = $this->createForm(AdminDiagNotificationType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Notification créée.');
            return $this->redirectToRoute('admin_diag_notification_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvelle Notification',
            'form' => $form->createView(),
            'cancel_route' => 'admin_diag_notification_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_diag_notification_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, DiagNotificationRepository $repo): Response
    {
        $item = $repo->find($id);
        if (!$item)
            throw $this->createNotFoundException();

        $form = $this->createForm(AdminDiagNotificationType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Notification modifiée.');
            return $this->redirectToRoute('admin_diag_notification_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Notification #' . $item->getId(),
            'form' => $form->createView(),
            'cancel_route' => 'admin_diag_notification_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_diag_notification_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, DiagNotificationRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Notification supprimée.');
        }
        return $this->redirectToRoute('admin_diag_notification_index');
    }
}
