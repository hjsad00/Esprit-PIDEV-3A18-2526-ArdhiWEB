<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\User;
use App\Repository\UserAndDiag\CommunityPostRepository;
use App\Service\UserAndDiag\ImgBBService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function profile(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        ValidatorInterface $validator,
        ImgBBService $imgBBService
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if ($request->isMethod('POST')) {
            $csrfToken = $request->request->get('_csrf_token', '');
            if (!$this->isCsrfTokenValid('profile_edit', $csrfToken)) {
                $this->addFlash('danger', 'Token CSRF invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_profile');
            }

            $nom = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));
            $phone = trim($request->request->get('phone', ''));
            $location = trim($request->request->get('location', ''));
            $role = trim($request->request->get('role', ''));
            $bio = trim($request->request->get('bio', ''));
            $password = $request->request->get('password', '');
            $passwordConfirm = $request->request->get('password_confirm', '');
            $twoFactorEnabled = $request->request->has('two_factor_enabled');

            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setPhone($phone ?: null);
            $user->setLocation($location ?: null);
            $user->setRole($role);
            $user->setBio($bio ?: null);
            $user->setTwoFactorEnabled($twoFactorEnabled);

            $avatarFile = $request->files->get('avatar');
            if ($avatarFile) {
                $imgUrl = $imgBBService->uploadImage($avatarFile);
                if ($imgUrl) {
                    $user->setAvatar($imgUrl);
                }
            }

            $bannerFile = $request->files->get('banner');
            if ($bannerFile) {
                $imgUrl = $imgBBService->uploadImage($bannerFile);
                if ($imgUrl) {
                    $user->setBanner($imgUrl);
                }
            }

            $validationGroups = ['Default'];
            if (!empty($password)) {
                $user->setPassword($password); // Temporarily set plain password for length validation
                $user->setPasswordConfirm($passwordConfirm);
                $validationGroups[] = 'profile_password';
            }

            $violationList = $validator->validate($user, null, $validationGroups);

            $errors = [];
            if (count($violationList) > 0) {
                foreach ($violationList as $violation) {
                    $errors[$violation->getPropertyPath()] = $violation->getMessage();
                }
            }

            if (!empty($errors)) {
                return $this->render('UserAndDiag/profile.html.twig', [
                    'user' => $user,
                    'errors' => $errors,
                ]);
            }

            if (!empty($password)) {
                $user->setPassword($passwordHasher->hashPassword($user, $password));
            }

            $entityManager->flush();
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès.');

            return $this->redirectToRoute('app_profile');
        }

        return $this->render('UserAndDiag/profile.html.twig', [
            'user' => $user,
            'errors' => [],
        ]);
    }

    #[Route('/profile/delete', name: 'app_profile_delete', methods: ['POST'])]
    public function deleteAccount(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        $csrfToken = $request->request->get('_token', '');

        if ($this->isCsrfTokenValid('delete_account', $csrfToken)) {
            // Log out the user by invalidating the session and token storage
            $request->getSession()->invalidate();
            if ($this->container->has('security.token_storage')) {
                $this->container->get('security.token_storage')->setToken(null);
            }

            $entityManager->remove($user);
            $entityManager->flush();

            $this->addFlash('success', 'Votre compte a été supprimé avec succès.');
            return $this->redirectToRoute('app_home');
        }

        $this->addFlash('danger', 'Token CSRF invalide.');
        return $this->redirectToRoute('app_profile');
    }

    #[Route('/profile/user/{id}', name: 'app_profile_show', methods: ['GET'])]
    public function show(User $user, CommunityPostRepository $postRepo): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        return $this->render('UserAndDiag/profile/show.html.twig', [
            'user' => $user,
            'posts' => $postRepo->findBy(['user' => $user], ['created_at' => 'DESC']),
        ]);
    }

    #[Route('/profile/user/{id}/block', name: 'app_profile_toggle_block', methods: ['POST'])]
    public function toggleBlock(User $userToBlock, Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        /** @var \App\Entity\UserAndDiag\User $currentUser */
        $currentUser = $this->getUser();

        if ($currentUser->getId() === $userToBlock->getId()) {
            $this->addFlash('danger', 'Vous ne pouvez pas vous bloquer vous-même.');
            return $this->redirectToRoute('app_profile_show', ['id' => $userToBlock->getId()]);
        }

        $csrfToken = $request->request->get('_token', '');
        if ($this->isCsrfTokenValid('toggle_block_' . $userToBlock->getId(), $csrfToken)) {
            if ($currentUser->isBlocking($userToBlock)) {
                $currentUser->removeBlockedUser($userToBlock);
                $this->addFlash('success', 'Utilisateur débloqué avec succès.');
            } else {
                $currentUser->addBlockedUser($userToBlock);
                $this->addFlash('success', 'Utilisateur bloqué avec succès.');
            }
            $em->flush();
        } else {
            $this->addFlash('danger', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('app_profile_show', ['id' => $userToBlock->getId()]);
    }
}
