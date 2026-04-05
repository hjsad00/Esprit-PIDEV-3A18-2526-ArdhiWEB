<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\CommunityLike;
use App\Entity\UserAndDiag\CommunityPost;
use App\Entity\UserAndDiag\CommunityComment;
use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunityLike>
 */
class CommunityLikeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityLike::class);
    }

    public function findPostVote(User $user, CommunityPost $post): ?CommunityLike
    {
        return $this->findOneBy(['user' => $user, 'post' => $post]);
    }

    public function findCommentVote(User $user, CommunityComment $comment): ?CommunityLike
    {
        return $this->findOneBy(['user' => $user, 'comment' => $comment]);
    }
}