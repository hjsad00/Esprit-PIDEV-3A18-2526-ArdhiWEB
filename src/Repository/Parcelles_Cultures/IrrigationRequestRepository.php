<?php

namespace App\Repository\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\IrrigationRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IrrigationRequest>
 */
class IrrigationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IrrigationRequest::class);
    }

    public function findByParcelle($parcelleId)
    {
        return $this->createQueryBuilder('ir')
            ->andWhere('ir.parcelle = :parcelle_id')
            ->setParameter('parcelle_id', $parcelleId)
            ->orderBy('ir.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getAverageTemperatureForParcelle($parcelleId, \DateTime $startDate = null, \DateTime $endDate = null)
    {
        $qb = $this->createQueryBuilder('ir')
            ->select('AVG(ir.temperature_moyenne) as avg_temp')
            ->andWhere('ir.parcelle = :parcelle_id')
            ->setParameter('parcelle_id', $parcelleId);

        if ($startDate) {
            $qb->andWhere('ir.date >= :start_date')
                ->setParameter('start_date', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('ir.date <= :end_date')
                ->setParameter('end_date', $endDate);
        }

        $result = $qb->getQuery()->getOneOrNullResult();
        return (float) ($result['avg_temp'] ?? 0);
    }

    public function getTotalPrecipitationsForParcelle($parcelleId, \DateTime $startDate = null, \DateTime $endDate = null)
    {
        $qb = $this->createQueryBuilder('ir')
            ->select('SUM(ir.precipitations) as total_precip')
            ->andWhere('ir.parcelle = :parcelle_id')
            ->setParameter('parcelle_id', $parcelleId);

        if ($startDate) {
            $qb->andWhere('ir.date >= :start_date')
                ->setParameter('start_date', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('ir.date <= :end_date')
                ->setParameter('end_date', $endDate);
        }

        $result = $qb->getQuery()->getOneOrNullResult();
        return (float) ($result['total_precip'] ?? 0);
    }
}
