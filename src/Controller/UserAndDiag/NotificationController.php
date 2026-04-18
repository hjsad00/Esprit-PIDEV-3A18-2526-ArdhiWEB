<?php

namespace App\Controller\UserAndDiag;

use App\Repository\UserAndDiag\DiagNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user-and-diag/notifications')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class NotificationController extends AbstractController
{
    #[Route('/unread', name: 'app_user_and_diag_notifications_unread', methods: ['GET'])]
    public function getUnread(DiagNotificationRepository $notifRepo): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $notifications = $notifRepo->findRecentByUser($user);

        $data = array_map(function ($n) {
            return [
                'id' => $n->getId(),
                'type' => $n->getType(),
                'message' => $n->getMessage(),
                'date' => $n->getCreatedAt()->format('d/m/Y H:i'),
                'relatedId' => $n->getRelatedEntityId(),
                'relatedType' => $n->getRelatedEntityType(),
                'isRead' => $n->isRead()
            ];
        }, $notifications);

        return $this->json(['notifications' => $data]);
    }

    #[Route('/mark-read', name: 'app_user_and_diag_notifications_mark_read', methods: ['POST'])]
    public function markAsRead(DiagNotificationRepository $notifRepo): JsonResponse
    {
        /** @var \App\Entity\UserAndDiag\User $user */
        $user = $this->getUser();
        $notifRepo->markAllAsReadForUser($user);

        return $this->json(['success' => true]);
    }
}
