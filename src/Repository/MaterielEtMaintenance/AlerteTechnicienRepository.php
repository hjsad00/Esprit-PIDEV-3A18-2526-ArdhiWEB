<?php

namespace App\Repository\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\AlerteTechnicien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AlerteTechnicien>
 */
class AlerteTechnicienRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AlerteTechnicien::class);
    }

    /**
     * Compte les alertes non lues pour un agriculteur donné.
     */
    public function countUnreadForAgriculteur(int $agriculteurId): int
    {
        return $this->createQueryBuilder('a')
            ->select('count(a.id)')
            ->where('a.agriculteur = :id')
            ->andWhere('a.statut = :statut')
            ->setParameter('id', $agriculteurId)
            ->setParameter('statut', 'non_lu')
            ->getQuery()
            ->getSingleScalarResult();
    }
}
