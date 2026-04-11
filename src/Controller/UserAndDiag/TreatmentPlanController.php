<?php

namespace App\Controller\UserAndDiag;

use App\Repository\UserAndDiag\TreatmentPlanRepository;
use App\Repository\UserAndDiag\TreatmentTaskRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/treatment-plan')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class TreatmentPlanController extends AbstractController
{
    #[Route('', name: 'app_user_and_diag_treatment_plan_list', methods: ['GET'])]
    public function list(
        TreatmentPlanRepository $planRepository,
        TreatmentTaskRepository $taskRepository,
        \App\Service\UserAndDiag\SubscriptionFeatureService $featureService
    ): Response {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();

        if (!$featureService->getFeatures($user)['accesPlanTraitement']) {
            $this->addFlash('danger', '🔒 Les Plans de Traitement ne sont disponibles qu\'avec un abonnement Premium. Veuillez mettre à niveau votre compte pour y accéder.');
            return $this->redirectToRoute('app_user_and_diag_subscription');
        }

        $plans = $planRepository->findByUser($user->getId());

        $plansWithProgress = [];
        foreach ($plans as $plan) {
            $totalTasks = $taskRepository->count(['treatmentPlan' => $plan]);
            $completedTasks = $taskRepository->count(['treatmentPlan' => $plan, 'status' => 'COMPLETED']);

            $progress = $totalTasks > 0 ? (int) (($completedTasks * 100) / $totalTasks) : 0;

            $plansWithProgress[] = [
                'plan' => $plan,
                'progress' => $progress,
                'total' => $totalTasks,
                'completed' => $completedTasks
            ];
        }

        return $this->render('UserAndDiag/treatment_plan/list.html.twig', [
            'plans' => $plansWithProgress
        ]);
    }
    #[Route('/{id}/details', name: 'app_user_and_diag_treatment_plan_show', methods: ['GET'])]
    public function show(\App\Entity\UserAndDiag\TreatmentPlan $plan, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        // 1. Security check
        if ($plan->getDiagnostic()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ce protocole ne vous appartient pas.');
        }

        // 2. Fetch tasks ordered chronologically
        $tasks = $em->getRepository(\App\Entity\UserAndDiag\TreatmentTask::class)->findBy(
            ['treatmentPlan' => $plan],
            ['day_offset' => 'ASC']
        );

        // 3. Pre-calculate "AR" markers based on your exact Java keyword logic
        $tasksWithMarkers = [];
        foreach ($tasks as $task) {
            $desc = strtolower($task->getTaskDescription());
            $locX = null;
            $locY = null;
            $locLabel = null;

            if (str_contains($desc, 'feuille')) {
                $locX = 30;
                $locY = 40;
                $locLabel = 'Feuilles';
            } elseif (str_contains($desc, 'tige')) {
                $locX = 50;
                $locY = 60;
                $locLabel = 'Tige Principale';
            } elseif (str_contains($desc, 'racine')) {
                $locX = 50;
                $locY = 90;
                $locLabel = 'Racines';
            } elseif (str_contains($desc, 'sol') || str_contains($desc, 'arroser')) {
                $locX = 50;
                $locY = 95;
                $locLabel = 'Sol';
            } elseif (str_contains($desc, 'fruit')) {
                $locX = 60;
                $locY = 30;
                $locLabel = 'Fruits';
            } elseif (str_contains($desc, 'fleur')) {
                $locX = 40;
                $locY = 20;
                $locLabel = 'Fleurs';
            }

            $tasksWithMarkers[] = [
                'entity' => $task,
                'marker' => $locX ? ['x' => $locX, 'y' => $locY, 'label' => $locLabel] : null
            ];
        }

        return $this->render('UserAndDiag/treatment_plan/show.html.twig', [
            'plan' => $plan,
            'diagnostic' => $plan->getDiagnostic(),
            'tasksWithMarkers' => $tasksWithMarkers
        ]);
    }
    #[Route('/task/{id}/complete', name: 'app_user_and_diag_treatment_task_complete', methods: ['POST'])]
    public function completeTask(\App\Entity\UserAndDiag\TreatmentTask $task, \Doctrine\ORM\EntityManagerInterface $em): \Symfony\Component\HttpFoundation\JsonResponse
    {
        $plan = $task->getTreatmentPlan();

        // Security check
        if ($plan->getDiagnostic()->getUser() !== $this->getUser()) {
            return $this->json(['error' => 'Accès refusé'], 403);
        }

        // Sequential Enforcement Check
        $previousTasks = $em->getRepository(\App\Entity\UserAndDiag\TreatmentTask::class)->createQueryBuilder('t')
            ->where('t.treatmentPlan = :plan')
            ->andWhere('t.dayOffset < :currentOffset')
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
        $em->flush(); // Save task status first

        // 2. Check if all tasks are now completed
        $pendingTasksCount = $em->getRepository(\App\Entity\UserAndDiag\TreatmentTask::class)->count([
            'treatmentPlan' => $plan,
            'status' => 'PENDING'
        ]);

        $planCompleted = false;

        // If there are no more pending tasks, finish the plan
        if ($pendingTasksCount === 0) {
            $plan->setStatus('COMPLETED');
            $em->flush(); // Save plan status
            $planCompleted = true;
        }

        // 3. Tell the frontend if the whole plan is done
        return $this->json([
            'success' => true,
            'plan_completed' => $planCompleted
        ]);
    }

    #[Route('/{id}/abandon', name: 'app_user_and_diag_treatment_plan_abandon', methods: ['POST'])]
    public function abandon(\App\Entity\UserAndDiag\TreatmentPlan $plan, \Doctrine\ORM\EntityManagerInterface $em): Response
    {
        // Security check
        if ($plan->getDiagnostic()->getUser() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Ce protocole ne vous appartient pas.');
        }

        $plan->setStatus('ABANDONED');
        $em->flush();

        $this->addFlash('success', 'Le protocole a été abandonné.');

        return $this->redirectToRoute('app_user_and_diag_treatment_plan_list');
    }
}
