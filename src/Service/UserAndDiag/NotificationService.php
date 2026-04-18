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

    public function notifyExpertReview(User $agriculteur, int $reviewId, string $reviewType): void
    {
        $typeLabel = $reviewType === 'DIAGNOSIS' ? 'diagnostic' : 'suivi';
        $message = sprintf("Un agronome a répondu à votre demande de %s.", $typeLabel);

        $this->createNotification($agriculteur, 'REVIEW', $message, $reviewId, 'REVIEW');
    }

    public function notifyPostLike(User $postAuthor, string $likerName, int $postId): void
    {
        $message = sprintf("%s a réagi à votre post dans la communauté.", $likerName);
        $this->createNotification($postAuthor, 'LIKE', $message, $postId, 'POST');
    }

    public function notifyCommentLike(User $commentAuthor, string $likerName, int $postId): void
    {
        $message = sprintf("%s a réagi à votre commentaire.", $likerName);
        $this->createNotification($commentAuthor, 'LIKE', $message, $postId, 'POST');
    }

    public function notifyMention(User $mentionedUser, string $authorName, int $postId): void
    {
        $message = sprintf("%s vous a mentionné dans la communauté.", $authorName);
        $this->createNotification($mentionedUser, 'MENTION', $message, $postId, 'POST');
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
