<?php

namespace App\Controller\UserAndDiag;

use App\Entity\UserAndDiag\User;
use App\Form\UserAndDiag\ChangePasswordFormType;
use App\Form\UserAndDiag\ResetPasswordRequestFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\Form\FormError;

#[Route('/reset-password')]
class ResetPasswordController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_forgot_password_request')]
    public function request(Request $request, MailerInterface $mailer, TranslatorInterface $translator): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();

            return $this->processSendingPasswordResetEmail($email, $mailer, $translator, $request);
        }

        return $this->render('UserAndDiag/reset_password/request.html.twig', [
            'requestForm' => $form,
        ]);
    }

    #[Route('/verify', name: 'app_check_email')]
    public function verify(Request $request, UserPasswordHasherInterface $passwordHasher): Response
    {
        $form = $this->createForm(ChangePasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $code = $form->get('code')->getData();
            $plainPassword = $form->get('plainPassword')->getData();

            $userRepository = $this->entityManager->getRepository(User::class);
            $user = $userRepository->findUserByResetCode($code);

            if (!$user) {
                $form->get('code')->addError(new FormError('Le code de vérification est invalide ou a expiré.'));
            } else {
                // The code is valid! Update the password
                $user->setResetPasswordCode(null);
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
                $this->entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été modifié avec succès.');
                return $this->redirectToRoute('app_login'); // Go to login after resetting
            }
        }

        return $this->render('UserAndDiag/reset_password/reset.html.twig', [
            'resetForm' => $form->createView(),
        ]);
    }

    #[Route('/reset/{token}', name: 'app_reset_password')]
    public function resetLegacyFallback()
    {
        // Keep this strictly to prevent older twig `url('app_reset_password')` calls from generating RouteNotFound errors 
        // if anything hasn't been cleaned up yet.
        return $this->redirectToRoute('app_check_email');
    }

    private function processSendingPasswordResetEmail(string $emailFormData, MailerInterface $mailer, TranslatorInterface $translator, Request $request): RedirectResponse
    {
        $userRepository = $this->entityManager->getRepository(User::class);
        $user = $userRepository->findOneBy(['email' => $emailFormData]);

        // Do not reveal whether a user account was found or not.
        if (!$user) {
            return $this->redirectToRoute('app_check_email');
        }

        $code = $userRepository->generateAndSaveNativeToken($user);

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@ardhi.com', 'Ardhi Admin'))
            ->to((string) $user->getEmail())
            ->subject('Votre code de vérification')
            ->htmlTemplate('UserAndDiag/reset_password/email.html.twig')
            ->context([
                'verificationCode' => $code, // Send the code strictly to Twig
            ])
        ;

        $mailer->send($email);

        return $this->redirectToRoute('app_check_email');
    }
}
