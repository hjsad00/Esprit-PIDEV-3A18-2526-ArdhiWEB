<?php

namespace App\Repository\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\CreditDossier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CreditDossier>
 */
class CreditDossierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CreditDossier::class);
    }

    public function findByParcelle($parcelleId)
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.parcelle = :parcelle_id')
            ->setParameter('parcelle_id', $parcelleId)
            ->orderBy('cd.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findLatestByParcelle($parcelleId)
    {
        return $this->createQueryBuilder('cd')
            ->andWhere('cd.parcelle = :parcelle_id')
            ->setParameter('parcelle_id', $parcelleId)
            ->orderBy('cd.created_at', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByNiveauRisque($niveauRisque): int
    {
        return $this->createQueryBuilder('cd')
            ->select('COUNT(cd.id)')
            ->andWhere('cd.niveau_risque = :niveau')
            ->setParameter('niveau', $niveauRisque)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getAverageScoreRisque(): float
    {
        $result = $this->createQueryBuilder('cd')
            ->select('AVG(cd.score_risque) as avg_score')
            ->getQuery()
            ->getOneOrNullResult();

        return (float) ($result['avg_score'] ?? 0);
    }
}
