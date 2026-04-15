<?php

namespace App\Controller\UserAndDiag;

use App\Repository\UserAndDiag\PreventionPlanRepository;
use App\Repository\UserAndDiag\PreventionTaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\UserAndDiag\PreventionPlan;
use App\Entity\UserAndDiag\PreventionTask;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/user-and-diag/prevention-plan')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PreventionPlanController extends AbstractController
{
    #[Route('', name: 'app_user_and_diag_prevention_plan_list', methods: ['GET'])]
    public function list(
        PreventionPlanRepository $planRepository,
        PreventionTaskRepository $taskRepository
    ): Response {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        $plans = $planRepository->findByUser($user->getId());

        $plansWithProgress = [];
        foreach ($plans as $plan) {
            $totalTasks = $taskRepository->count(['preventionPlan' => $plan]);
            $completedTasks = $taskRepository->count(['preventionPlan' => $plan, 'status' => 'COMPLETED']);

            $progress = $totalTasks > 0 ? (int) (($completedTasks * 100) / $totalTasks) : 0;

            $plansWithProgress[] = [
                'plan' => $plan,
                'progress' => $progress,
                'total' => $totalTasks,
                'completed' => $completedTasks
            ];
        }

        return $this->render('UserAndDiag/prevention_plan/list.html.twig', [
            'plans' => $plansWithProgress
        ]);
    }

    #[Route('/{id}/details', name: 'app_user_and_diag_prevention_plan_show', methods: ['GET'])]
    public function show(PreventionPlan $plan, EntityManagerInterface $em): Response
    {
        // Security check
        if ($plan->getReport()->getScan()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ce plan ne vous appartient pas.');
        }

        // Fetch tasks ordered chronologically
        $tasks = $em->getRepository(PreventionTask::class)->findBy(
            ['preventionPlan' => $plan],
            ['day_offset' => 'ASC']
        );

        return $this->render('UserAndDiag/prevention_plan/show.html.twig', [
            'plan' => $plan,
            'report' => $plan->getReport(),
            'tasks' => $tasks
        ]);
    }

    #[Route('/task/{id}/complete', name: 'app_user_and_diag_prevention_task_complete', methods: ['POST'])]
    public function completeTask(PreventionTask $task, EntityManagerInterface $em): JsonResponse
    {
        $plan = $task->getPreventionPlan();

        // Security check
        if ($plan->getReport()->getScan()->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        // Sequential Enforcement Check
        $previousTasks = $em->getRepository(PreventionTask::class)->createQueryBuilder('t')
            ->where('t.preventionPlan = :plan')
            ->andWhere('t.day_offset < :currentOffset')
            ->andWhere('t.status != :completedStatus')
            ->setParameter('plan', $plan)
            ->setParameter('currentOffset', $task->getDayOffset())
            ->setParameter('completedStatus', 'COMPLETED')
            ->getQuery()
            ->getResult();

        if (count($previousTasks) > 0) {
            return $this->json([
                'success' => false,
                'error' => 'Ordre séquentiel: Vous devez terminer les tâches précédentes avant de valider celle-ci.'
            ], 400);
        }

        // 1. Complete the task
        $task->setStatus('COMPLETED');
        $task->setCompletedAt(new \DateTime());
        $em->flush();

        // 2. Check if all tasks are now completed
        $pendingTasksCount = $em->getRepository(PreventionTask::class)->count([
            'preventionPlan' => $plan,
            'status' => 'PENDING'
        ]);

        $planCompleted = false;

        if ($pendingTasksCount === 0) {
            $plan->setStatus('COMPLETED');
            $em->flush();
            $planCompleted = true;
        }

        return $this->json([
            'success' => true,
            'plan_completed' => $planCompleted
        ]);
    }

    #[Route('/{id}/abandon', name: 'app_user_and_diag_prevention_plan_abandon', methods: ['POST'])]
    public function abandon(PreventionPlan $plan, EntityManagerInterface $em): Response
    {
        // Security check
        if ($plan->getReport()->getScan()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ce plan ne vous appartient pas.');
        }

        $plan->setStatus('ABANDONED');
        $em->flush();

        $this->addFlash('success', 'Le plan de prévention a été abandonné.');

        return $this->redirectToRoute('app_user_and_diag_prevention_plan_list');
    }

    #[Route('/{id}/whatsapp', name: 'app_user_and_diag_prevention_plan_whatsapp', methods: ['POST'])]
    public function whatsappReminder(
        PreventionPlan $plan,
        EntityManagerInterface $em,
        \App\Service\UserAndDiag\WhatsAppService $whatsAppService
    ): JsonResponse {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        // Security & Phone Check
        if ($plan->getReport()->getScan()->getUser() !== $user) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        $phone = $user->getPhone();
        if (!$phone) {
            return $this->json(['error' => 'Veuillez ajouter un numéro de téléphone à votre profil pour utiliser WhatsApp.'], 400);
        }

        // Fetch pending tasks
        $tasks = $em->getRepository(PreventionTask::class)->findBy(
            ['preventionPlan' => $plan, 'status' => 'PENDING'],
            ['day_offset' => 'ASC']
        );

        if (empty($tasks)) {
            return $this->json(['error' => 'Aucune tâche en attente à rappeler.'], 400);
        }

        // Construct WhatsApp Message
        $message = "🛡️ *Rappel Ardhi : Plan de Prévention*\n";
        $message .= "Voici vos prochaines interventions pour : _" . $plan->getTitle() . "_\n\n";

        foreach ($tasks as $task) {
            $message .= "🗓️ *Jour " . $task->getDayOffset() . "* : " . $task->getTaskDescription() . "\n";
        }

        $message .= "\nConnectez-vous sur l'application pour valider vos tâches !";

        // Send the message
        $success = $whatsAppService->sendWhatsAppMessage($phone, $message);

        if ($success) {
            return $this->json(['success' => true, 'message' => 'Rappel WhatsApp envoyé avec succès !']);
        }

        return $this->json([
            'error' => "L'envoi a échoué. Assurez-vous d'avoir rejoint la Sandbox Twilio (envoyez le code au numéro Sandbox)."
        ], 500);
    }

    #[Route('/{id}/ask-expert', name: 'app_user_and_diag_prevention_plan_ask_expert', methods: ['POST'])]
    public function askExpert(
        PreventionPlan $plan,
        Request $request,
        EntityManagerInterface $em,
        \App\Service\UserAndDiag\ImgBBService $imgBBService
    ): JsonResponse {
        // Security Check
        if ($plan->getReport()->getScan()->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        // Anti-Spam Check
        $existingReview = $em->getRepository(\App\Entity\UserAndDiag\Review::class)->findOneBy([
            'prevention_plan' => $plan,
            'status' => 'PENDING'
        ]);

        if ($existingReview) {
            return $this->json(['error' => 'Vous avez déjà une demande en attente d\'analyse par nos experts.'], 400);
        }

        // Process the Image
        $file = $request->files->get('image');
        if (!$file)
            return $this->json(['error' => 'Une image est requise pour demander l\'avis d\'un expert.'], 400);

        try {
            $imgUrl = $imgBBService->uploadImage($file);

            // Fetch the user's most recent diagnostic (Review.diagnostic is NOT NULL)
            $latestDiagnostic = $em->getRepository(\App\Entity\UserAndDiag\Diagnostic::class)
                ->findOneBy(['user' => $this->getUser()], ['id' => 'DESC']);

            // Create the Review Ticket
            $review = new \App\Entity\UserAndDiag\Review();
            if ($latestDiagnostic) {
                $review->setDiagnostic($latestDiagnostic);
            }
            $review->setPreventionPlan($plan);
            $review->setReviewType('PREVENTION');
            $review->setStatus('PENDING');
            $review->setPhotoUrl($imgUrl);
            $review->setCreatedAt(new \DateTime());

            $em->persist($review);
            $em->flush();

            return $this->json([
                'success' => true,
                'message' => 'Votre photo a été envoyée avec succès ! Nos agronomes l\'analyseront sous peu.'
            ]);

        } catch (\Exception $e) {
            return $this->json(['error' => 'Erreur technique lors de l\'envoi: ' . $e->getMessage()], 500);
        }
    }
}
