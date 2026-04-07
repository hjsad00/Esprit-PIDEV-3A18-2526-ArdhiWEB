<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\Diagnostic;
use App\Form\UserAndDiag\Admin\AdminDiagnosticType;
use App\Repository\UserAndDiag\DiagnosticRepository;
use App\Service\UserAndDiag\ImgBBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/diagnostics')]
class AdminDiagnosticController extends AbstractController
{
    #[Route('', name: 'admin_diagnostic_index')]
    public function index(DiagnosticRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Diagnostics',
            'icon' => 'bi-search',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Image', 'field' => 'imageScannee', 'type' => 'image'],
                ['label' => 'Date', 'field' => 'dateScan', 'type' => 'date'],
                ['label' => 'Résultat IA', 'field' => 'resultatIa'],
                ['label' => 'Confiance', 'field' => 'confiance'],
                ['label' => 'Sévérité', 'field' => 'severity', 'type' => 'badge', 'color' => '#e74c3c'],
                ['label' => 'User', 'field' => 'user', 'type' => 'relation', 'display' => 'email'],
            ],
            'new_route' => 'admin_diagnostic_new',
            'edit_route' => 'admin_diagnostic_edit',
            'delete_route' => 'admin_diagnostic_delete',
        ]);
    }

    #[Route('/new', name: 'admin_diagnostic_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, ImgBBService $imgBBService): Response
    {
        $item = new Diagnostic();
        $form = $this->createForm(AdminDiagnosticType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $url = $imgBBService->uploadImage($imageFile);
                if ($url) {
                    $item->setImageScannee($url);
                } else {
                    $this->addFlash('danger', 'Échec de l\'upload de l\'image sur ImgBB. Le post sera sauvegardé sans nouvelle image.');
                }
            }
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Diagnostic créé.');
            return $this->redirectToRoute('admin_diagnostic_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Diagnostic',
            'form' => $form,
            'cancel_route' => 'admin_diagnostic_index',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_diagnostic_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, DiagnosticRepository $repo, ImgBBService $imgBBService): Response
    {
        $item = $repo->find($id);
        if (!$item) {
            throw $this->createNotFoundException();
        }

        $form = $this->createForm(AdminDiagnosticType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('imageFile')->getData();
            if ($imageFile) {
                $url = $imgBBService->uploadImage($imageFile);
                if ($url) {
                    $item->setImageScannee($url);
                } else {
                    $this->addFlash('danger', 'Échec de l\'upload de l\'image sur ImgBB.');
                }
            }
            $em->flush();
            $this->addFlash('success', 'Diagnostic modifié.');
            return $this->redirectToRoute('admin_diagnostic_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Diagnostic #' . $item->getId(),
            'form' => $form,
            'cancel_route' => 'admin_diagnostic_index',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_diagnostic_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, DiagnosticRepository $repo): Response
    {
        $item = $repo->find($id);
        if ($item && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($item);
            $em->flush();
            $this->addFlash('success', 'Diagnostic supprimé.');
        }
        return $this->redirectToRoute('admin_diagnostic_index');
    }
}
