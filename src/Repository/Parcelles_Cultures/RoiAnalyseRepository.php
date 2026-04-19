<?php

namespace App\Repository\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\RoiAnalyse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RoiAnalyseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoiAnalyse::class);
    }

    /**
     * Trouve les analyses d'une parcelle
     */
    public function findByParcelle($parcelle)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.parcelle = :parcelle')
            ->setParameter('parcelle', $parcelle)
            ->orderBy('r.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve l'analyse la plus récente d'une parcelle
     */
    public function findLatestByParcelle($parcelle)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.parcelle = :parcelle')
            ->setParameter('parcelle', $parcelle)
            ->orderBy('r.created_at', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Statistiques par parcelle
     */
    public function getStatisticsByParcelle($parcelle)
    {
        return $this->createQueryBuilder('r')
            ->select('AVG(r.roi) as avg_roi, MAX(r.roi) as max_roi, MIN(r.roi) as min_roi, COUNT(r.id) as total')
            ->andWhere('r.parcelle = :parcelle')
            ->setParameter('parcelle', $parcelle)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Historique d'analyses
     */
    public function getAnalysisHistory($parcelle, $limit = 10)
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.parcelle = :parcelle')
            ->setParameter('parcelle', $parcelle)
            ->orderBy('r.created_at', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
