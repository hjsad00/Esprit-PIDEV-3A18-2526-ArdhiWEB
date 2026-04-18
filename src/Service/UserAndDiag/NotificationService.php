<?php

namespace App\Service\UserAndDiag;

use App\Entity\UserAndDiag\DiagNotification;
use App\Entity\UserAndDiag\User;
use Doctrine\ORM\EntityManagerInterface;

class NotificationService
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function notifyExpertReview(User $agriculteur, \App\Entity\UserAndDiag\Review $review): void
    {
        $typeLabel = $review->getReviewType() === 'DIAGNOSIS' ? 'diagnostic' : 'suivi';
        $message = sprintf("Un agronome a répondu à votre demande de %s.", $typeLabel);

        $relatedId = null;
        $relatedType = 'DIAGNOSIS';

        if ($review->getReviewType() === 'PROGRESS' || $review->getReviewType() === 'PREVENTION') {
            if ($review->getTreatmentPlan()) {
                $relatedId = $review->getTreatmentPlan()->getId();
                $relatedType = 'TREATMENT';
            } elseif ($review->getPreventionPlan()) {
                $relatedId = $review->getPreventionPlan()->getId();
                $relatedType = 'PREVENTION';
            }
        }

        $this->createNotification($agriculteur, 'REVIEW', $message, $relatedId, $relatedType);
    }

    public function notifyPostLike(User $postAuthor, string $likerName, int $postId): void
    {
        $message = sprintf("%s a aimé votre publication.", $likerName);
        $this->createNotification($postAuthor, 'LIKE', $message, $postId, 'POST');
    }

    public function notifyPostDislike(User $postAuthor, string $likerName, int $postId): void
    {
        $message = sprintf("%s a n'a pas aimé (dislike) votre publication.", $likerName);
        $this->createNotification($postAuthor, 'DISLIKE', $message, $postId, 'POST');
    }

    public function notifyCommentLike(User $commentAuthor, string $likerName, int $commentId): void
    {
        $message = sprintf("%s a aimé votre commentaire.", $likerName);
        $this->createNotification($commentAuthor, 'LIKE', $message, $commentId, 'COMMENT');
    }

    public function notifyCommentDislike(User $commentAuthor, string $likerName, int $commentId): void
    {
        $message = sprintf("%s a n'a pas aimé (dislike) votre commentaire.", $likerName);
        $this->createNotification($commentAuthor, 'DISLIKE', $message, $commentId, 'COMMENT');
    }

    public function notifyPostComment(User $postAuthor, string $commenterName, int $commentId): void
    {
        $message = sprintf("%s a commenté votre publication.", $commenterName);
        $this->createNotification($postAuthor, 'COMMENT', $message, $commentId, 'COMMENT');
    }

    public function notifyCommentReply(User $commentAuthor, string $replierName, int $commentId): void
    {
        $message = sprintf("%s a répondu à votre commentaire.", $replierName);
        $this->createNotification($commentAuthor, 'COMMENT_REPLY', $message, $commentId, 'COMMENT');
    }

    public function notifyMention(User $mentionedUser, string $authorName, int $entityId, string $entityType = 'POST'): void
    {
        $message = sprintf("%s vous a mentionné dans la communauté.", $authorName);
        $this->createNotification($mentionedUser, 'MENTION', $message, $entityId, $entityType);
    }

    private function createNotification(User $user, string $type, string $message, ?int $relatedId = null, ?string $relatedType = null): void
    {
        $notification = new DiagNotification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setMessage($message);
        $notification->setRelatedEntityId($relatedId);
        $notification->setRelatedEntityType($relatedType);

        $this->em->persist($notification);
        $this->em->flush();
    }
}
