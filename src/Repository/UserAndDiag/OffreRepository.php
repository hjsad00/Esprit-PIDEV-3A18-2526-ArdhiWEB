<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\Offre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Offre>
 */
class OffreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offre::class);
    }

    /**
     * @return Offre[]
     */
    public function findActiveOffers(): array
    {
        return $this->createQueryBuilder('o')
            ->where('o.est_active = :active')
            ->setParameter('active', true)
            ->orderBy('o.prix_mensuel', 'ASC')
            ->getQuery()
            ->getResult();
    }
}