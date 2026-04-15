<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\ModerationAudit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ModerationAuditRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ModerationAudit::class);
    }

    /**
     * Returns all audit logs ordered by most recent first.
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
