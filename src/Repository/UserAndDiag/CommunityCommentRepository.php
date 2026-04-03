<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\CommunityComment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommunityComment>
 *
 * @method CommunityComment|null find($id, $lockMode = null, $lockVersion = null)
 * @method CommunityComment|null findOneBy(array $criteria, array $orderBy = null)
 * @method CommunityComment[]    findAll()
 * @method CommunityComment[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommunityCommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommunityComment::class);
    }
}