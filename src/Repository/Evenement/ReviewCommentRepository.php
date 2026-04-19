<?php

namespace App\Repository\Evenement;

use App\Entity\Evenement\ReviewComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReviewComment>
 */
class ReviewCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReviewComment::class);
    }

    /**
     * Find replies to a specific review (participation)
     */
    public function findByParticipation($participation)
    {
        return $this->createQueryBuilder('rc')
            ->where('rc.participation = :participation')
            ->andWhere('rc.parentComment IS NULL')
            ->setParameter('participation', $participation)
            ->orderBy('rc.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find replies to a specific comment
     */
    public function findReplies(ReviewComment $comment)
    {
        return $this->createQueryBuilder('rc')
            ->where('rc.parentComment = :parent')
            ->setParameter('parent', $comment)
            ->orderBy('rc.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
