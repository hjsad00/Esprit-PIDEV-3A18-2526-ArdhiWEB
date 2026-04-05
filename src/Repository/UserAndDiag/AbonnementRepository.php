<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\Abonnement;
use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Abonnement>
 */
class AbonnementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Abonnement::class);
    }

    public function findActiveByUser(User $user): ?Abonnement
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->andWhere('a.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', 'ACTIF')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}