<?php

namespace App\Controller\UserAndDiag;

use App\Repository\UserAndDiag\PreventionPlanRepository;
use App\Repository\UserAndDiag\TreatmentPlanRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/calendar')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class PlanCalendarController extends AbstractController
{
    #[Route('', name: 'app_user_and_diag_calendar', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('UserAndDiag/calendar/index.html.twig');
    }

    #[Route('/api', name: 'app_user_and_diag_calendar_api', methods: ['GET'])]
    public function calendarApi(
        TreatmentPlanRepository $treatmentRepo,
        PreventionPlanRepository $preventionRepo
    ): JsonResponse {
        $events = [];

        // Fetch ACTIVE or COMPLETED plans (ignoring abandoned usually, but let's fetch active for upcoming tasks)
        // Treatment Plans
        $treatmentPlans = $treatmentRepo->findBy(['status' => 'ACTIVE']);
        foreach ($treatmentPlans as $plan) {
            $startDate = $plan->getStartDate();
            if (!$startDate)
                continue;

            $diagName = $plan->getDiagnostic() ? $plan->getDiagnostic()->getResultatIa() : 'Soin';

            foreach ($plan->getTasks() as $task) {
                // Task date = startDate + day_offset days
                $taskDate = \DateTime::createFromInterface($startDate);
                $taskDate->modify('+' . $task->getDayOffset() . ' days');

                $color = $task->getStatus() === 'COMPLETED' ? '#2ecc71' : ($task->getStatus() === 'MISSED' ? '#e74c3c' : '#3498db');

                $events[] = [
                    'id' => 't_' . $task->getId(),
                    'title' => $task->getTaskDescription(),
                    'start' => $taskDate->format('Y-m-d'),
                    'allDay' => true,
                    'color' => $color,
                    'extendedProps' => [
                        'type' => 'treatment',
                        'planName' => $diagName,
                        'status' => $task->getStatus(),
                        'planId' => $plan->getId()
                    ]
                ];
            }
        }

        // Prevention Plans
        $preventionPlans = $preventionRepo->findBy(['status' => 'ACTIVE']);
        foreach ($preventionPlans as $plan) {
            $startDate = $plan->getStartDate() ?: $plan->getCreatedAt();
            if (!$startDate)
                continue;

            $planName = $plan->getTitle() ?: 'Prévention';

            foreach ($plan->getTasks() as $task) {
                $taskDate = \DateTime::createFromInterface($startDate);
                $taskDate->modify('+' . $task->getDayOffset() . ' days');

                $color = $task->getStatus() === 'COMPLETED' ? '#2ecc71' : ($task->getStatus() === 'MISSED' ? '#e74c3c' : '#f39c12');

                $events[] = [
                    'id' => 'p_' . $task->getId(),
                    'title' => $task->getTaskDescription(),
                    'start' => $taskDate->format('Y-m-d'),
                    'allDay' => true,
                    'color' => $color,
                    'extendedProps' => [
                        'type' => 'prevention',
                        'planName' => $planName,
                        'status' => $task->getStatus(),
                        'planId' => $plan->getId()
                    ]
                ];
            }
        }

        return $this->json($events);
    }
}
