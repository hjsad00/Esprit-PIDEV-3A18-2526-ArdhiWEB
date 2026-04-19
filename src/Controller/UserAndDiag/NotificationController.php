<?php

namespace App\Controller\UserAndDiag;

use App\Repository\UserAndDiag\DiagNotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
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

    #[Route('/read-and-redirect/{id}', name: 'app_user_and_diag_notifications_read_redirect', methods: ['GET'])]
    public function readAndRedirect(\App\Entity\UserAndDiag\DiagNotification $notif, EntityManagerInterface $em): Response
    {
        if ($notif->getUser() === $this->getUser()) {
            $notif->setRead(true);
            $em->flush();
        }

        $link = '/';
        $rt = $notif->getRelatedEntityType();
        $rid = $notif->getRelatedEntityId();

        if ($rt === 'POST')
            $link = '/user-and-diag/community/' . $rid;
        elseif ($rt === 'COMMENT' || $rt === 'COMMENT_REPLY') {
            $comment = $em->getRepository(\App\Entity\UserAndDiag\CommunityComment::class)->find($rid);
            if ($comment && $comment->getPost()) {
                $link = '/user-and-diag/community/' . $comment->getPost()->getId() . '#comment-' . $comment->getId();
            } else {
                // Flash message or default fallback if comment was deleted
                $link = '/user-and-diag/community';
            }
        } elseif ($rt === 'TREATMENT')
            $link = '/user-and-diag/treatment-plan/' . $rid . '/details';
        elseif ($rt === 'PREVENTION')
            $link = '/user-and-diag/prevention-plan/' . $rid . '/details';
        elseif ($rt === 'DIAGNOSIS')
            $link = '/user-and-diag/history';

        return $this->redirect($link);
    }
}
