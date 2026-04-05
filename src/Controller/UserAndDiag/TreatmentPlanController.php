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
}
