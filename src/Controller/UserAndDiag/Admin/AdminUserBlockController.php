<?php
namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\UserBlock;
use App\Form\UserAndDiag\Admin\AdminUserBlockType;
use App\Repository\UserAndDiag\UserBlockRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/user-blocks')]
class AdminUserBlockController extends AbstractController
{
    #[Route('', name: 'admin_user_block_index')]
    public function index(UserBlockRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Blocages Utilisateurs',
            'icon' => 'bi-slash-circle-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'Bloqueur', 'field' => 'blocker', 'type' => 'relation', 'display' => 'email'],
                ['label' => 'Bloqué', 'field' => 'blocked', 'type' => 'relation', 'display' => 'email']
            ],
            'new_route' => 'admin_user_block_new',
            'edit_route' => 'admin_user_block_edit', // NOTE: composite keys might make edit/delete tricky but provided for template compat
            'delete_route' => 'admin_user_block_delete',
        ]);
    }

    #[Route('/new', name: 'admin_user_block_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $item = new UserBlock();
        $form = $this->createForm(AdminUserBlockType::class, $item);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($item);
            $em->flush();
            $this->addFlash('success', 'Blocage créé.');
            return $this->redirectToRoute('admin_user_block_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouveau Blocage',
            'form' => $form->createView(),
            'cancel_route' => 'admin_user_block_index',
        ]);
    }
}
