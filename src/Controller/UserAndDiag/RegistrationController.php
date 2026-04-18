<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\User;
use App\Repository\UserAndDiag\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        UserRepository $userRepository,
        Security $security,
        ValidatorInterface $validator,
    ): Response {
        // Redirect if already fully logged in
        if ($this->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $this->redirectToRoute('app_home');
        }

        if ($request->isMethod('POST')) {
            $nom = trim($request->request->get('nom', ''));
            $prenom = trim($request->request->get('prenom', ''));
            $email = trim($request->request->get('email', ''));
            $role = trim($request->request->get('role', 'AGRICULTEUR'));
            $phone = trim($request->request->get('phone', ''));
            $location = trim($request->request->get('location', ''));
            $password = $request->request->get('password', '');
            $passwordConfirm = $request->request->get('password_confirm', '');
            $csrfToken = $request->request->get('_csrf_token', '');

            // CSRF check
            if (!$this->isCsrfTokenValid('register', $csrfToken)) {
                $this->addFlash('danger', 'Token CSRF invalide, veuillez réessayer.');
                return $this->redirectToRoute('app_register');
            }

            // reCAPTCHA check
            $recaptchaToken = $request->request->get('g-recaptcha-response');
            if (empty($recaptchaToken)) {
                $this->addFlash('danger', 'Échec de validation reCAPTCHA (Jeton vide).');
                return $this->redirectToRoute('app_register');
            }

            $secret = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, "https://www.google.com/recaptcha/api/siteverify");
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'secret' => $secret,
                'response' => $recaptchaToken
            ]));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $verifyResponse = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            $data = json_decode((string) $verifyResponse, true);

            if (empty($data['success']) || (isset($data['score']) && $data['score'] < 0.5)) {
                $this->addFlash('danger', 'Votre score reCAPTCHA est trop faible. Details: ' . substr((string) $verifyResponse, 0, 100) . ' cURL Error: ' . $error);
                return $this->redirectToRoute('app_register');
            }

            // Create user object for validation
            $user = new User();
            $user->setNom($nom);
            $user->setPrenom($prenom);
            $user->setEmail($email);
            $user->setRole($role);
            $user->setPhone($phone ?: null);
            $user->setLocation($location ?: null);
            $user->setPassword($password); // Plain password for validation
            $user->setPasswordConfirm($passwordConfirm);

            // Validate the entity
            $violationList = $validator->validate($user, null, ['registration', 'Default']);

            $errors = [];
            if (count($violationList) > 0) {
                foreach ($violationList as $violation) {
                    $path = $violation->getPropertyPath();
                    $errors[$path] = $violation->getMessage();
                }
            }

            if (!empty($errors)) {
                return $this->render('UserAndDiag/register.html.twig', [
                    'last_nom' => $nom,
                    'last_prenom' => $prenom,
                    'last_email' => $email,
                    'last_role' => $role,
                    'last_phone' => $phone,
                    'last_location' => $location,
                    'errors' => $errors,
                ], new Response(null, Response::HTTP_UNPROCESSABLE_ENTITY));
            }

            // If valid, hash password and save
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            $entityManager->persist($user);
            $entityManager->flush();

            // Auto-login after registration
            $security->login($user, 'form_login', 'main');

            $this->addFlash('success', 'Inscription réussie ! Bienvenue ' . $prenom . ' !');
            return $this->redirectToRoute('app_home');
        }

        return $this->render('UserAndDiag/register.html.twig', [
            'last_nom' => '',
            'last_prenom' => '',
            'last_email' => '',
            'last_role' => 'AGRICULTEUR',
            'last_phone' => '',
            'last_location' => '',
            'errors' => [],
        ]);
    }
}
