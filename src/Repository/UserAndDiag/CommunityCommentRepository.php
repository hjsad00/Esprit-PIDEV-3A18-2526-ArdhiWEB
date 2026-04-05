<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\CommunityComment;
use App\Entity\UserAndDiag\CommunityPost;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunityComment>
 */
class CommunityCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityComment::class);
    }

    /**
     * @return CommunityComment[]
     */
    public function findByPost(CommunityPost $post): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.post = :post')
            ->setParameter('post', $post)
            ->orderBy('c.created_at', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countByPost(CommunityPost $post): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.post = :post')
            ->setParameter('post', $post)
            ->getQuery()
            ->getSingleScalarResult();
    }
}