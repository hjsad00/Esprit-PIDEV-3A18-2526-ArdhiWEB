<?php

namespace App\Controller\UserAndDiag\Admin;

use App\Entity\UserAndDiag\User;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/users')]
class AdminUserController extends AbstractController
{
    #[Route('', name: 'admin_user_index')]
    public function index(UserRepository $repo): Response
    {
        return $this->render('UserAndDiag/admin/crud/list.html.twig', [
            'page_title' => 'Utilisateurs',
            'icon' => 'bi-people-fill',
            'items' => $repo->findAll(),
            'columns' => [
                ['label' => 'ID', 'field' => 'id'],
                ['label' => 'Email', 'field' => 'email'],
                ['label' => 'Nom', 'field' => 'nom'],
                ['label' => 'Prénom', 'field' => 'prenom'],
                ['label' => 'Rôle', 'field' => 'role', 'type' => 'badge', 'color' => '#116530'],
                ['label' => 'Points', 'field' => 'points'],
                ['label' => 'Niveau', 'field' => 'level'],
                ['label' => 'Téléphone', 'field' => 'phone'],
            ],
            'new_route' => 'admin_user_new',
            'edit_route' => 'admin_user_edit',
            'delete_route' => 'admin_user_delete',
        ]);
    }

    #[Route('/new', name: 'admin_user_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $hasher): Response
    {
        if ($request->isMethod('POST')) {
            $user = new User();
            $user->setNom($request->request->get('nom', ''));
            $user->setPrenom($request->request->get('prenom', ''));
            $user->setEmail($request->request->get('email', ''));
            $user->setRole($request->request->get('role', 'AGRICULTEUR'));
            $user->setPhone($request->request->get('phone') ?: null);
            $user->setLocation($request->request->get('location') ?: null);
            $user->setPoints((int) $request->request->get('points', 0));
            $user->setLevel((int) $request->request->get('level', 1));
            $pwd = $request->request->get('password', '');
            if ($pwd) {
                $user->setPassword($hasher->hashPassword($user, $pwd));
            }
            $em->persist($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur créé.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Nouvel Utilisateur',
            'fields' => $this->getFields(),
            'cancel_route' => 'admin_user_index',
            'csrf_token_id' => 'user_form',
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_user_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, EntityManagerInterface $em, UserRepository $repo, UserPasswordHasherInterface $hasher): Response
    {
        $user = $repo->find($id);
        if (!$user) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $user->setNom($request->request->get('nom', ''));
            $user->setPrenom($request->request->get('prenom', ''));
            $user->setEmail($request->request->get('email', ''));
            $user->setRole($request->request->get('role', 'AGRICULTEUR'));
            $user->setPhone($request->request->get('phone') ?: null);
            $user->setLocation($request->request->get('location') ?: null);
            $user->setPoints((int) $request->request->get('points', 0));
            $user->setLevel((int) $request->request->get('level', 1));
            $pwd = $request->request->get('password', '');
            if ($pwd) {
                $user->setPassword($hasher->hashPassword($user, $pwd));
            }
            $em->flush();
            $this->addFlash('success', 'Utilisateur modifié.');
            return $this->redirectToRoute('admin_user_index');
        }

        return $this->render('UserAndDiag/admin/crud/form.html.twig', [
            'page_title' => 'Modifier Utilisateur #' . $user->getId(),
            'fields' => $this->getFields(true),
            'item' => $user,
            'cancel_route' => 'admin_user_index',
            'csrf_token_id' => 'user_form',
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, EntityManagerInterface $em, UserRepository $repo): Response
    {
        $user = $repo->find($id);
        if ($user && $this->isCsrfTokenValid('delete' . $id, $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé.');
        }
        return $this->redirectToRoute('admin_user_index');
    }

    private function getFields(bool $isEdit = false): array
    {
        $fields = [
            ['name' => 'nom', 'label' => 'Nom', 'getter' => 'nom', 'required' => true],
            ['name' => 'prenom', 'label' => 'Prénom', 'getter' => 'prenom', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'getter' => 'email', 'required' => true],
            [
                'name' => 'role',
                'label' => 'Rôle',
                'getter' => 'role',
                'type' => 'select',
                'required' => true,
                'options' => [
                    ['value' => 'ADMIN', 'label' => 'Admin'],
                    ['value' => 'AGRICULTEUR', 'label' => 'Agriculteur'],
                    ['value' => 'CLIENT', 'label' => 'Client'],
                    ['value' => 'AGRONOME', 'label' => 'Agronome'],
                ]
            ],
            ['name' => 'phone', 'label' => 'Téléphone', 'getter' => 'phone'],
            ['name' => 'location', 'label' => 'Localisation', 'getter' => 'location'],
            ['name' => 'points', 'label' => 'Points', 'getter' => 'points', 'type' => 'number', 'default' => '0'],
            ['name' => 'level', 'label' => 'Niveau', 'getter' => 'level', 'type' => 'number', 'default' => '1'],
            ['name' => 'password', 'label' => 'Mot de passe' . ($isEdit ? ' (laisser vide pour ne pas changer)' : ''), 'getter' => 'password', 'type' => 'password', 'required' => !$isEdit, 'placeholder' => $isEdit ? 'Laisser vide pour garder le mot de passe actuel' : ''],
        ];
        return $fields;
    }
}
