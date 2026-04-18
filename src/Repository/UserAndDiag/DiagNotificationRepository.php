<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\DiagNotification;
use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DiagNotification>
 */
class DiagNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DiagNotification::class);
    }

    /**
     * @return DiagNotification[] Returns an array of DiagNotification objects
     */
    public function findRecentByUser(User $user): array
    {
        return $this->createQueryBuilder('d')
            ->andWhere('d.user = :val')
            ->setParameter('val', $user)
            ->orderBy('d.createdAt', 'DESC')
            ->setMaxResults(50)
            ->getQuery()
            ->getResult()
        ;
    }

    public function markAllAsReadForUser(User $user): void
    {
        $this->createQueryBuilder('d')
            ->update()
            ->set('d.isRead', 'true')
            ->andWhere('d.user = :val')
            ->setParameter('val', $user)
            ->getQuery()
            ->execute()
        ;
    }
}
