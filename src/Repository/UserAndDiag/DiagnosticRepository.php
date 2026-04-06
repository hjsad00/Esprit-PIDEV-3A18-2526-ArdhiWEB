<?php

namespace App\Repository\UserAndDiag;

use App\Entity\UserAndDiag\Diagnostic;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Diagnostic>
 *
 * @method Diagnostic|null find($id, $lockMode = null, $lockVersion = null)
 * @method Diagnostic|null findOneBy(array $criteria, array $orderBy = null)
 * @method Diagnostic[]    findAll()
 * @method Diagnostic[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class DiagnosticRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Diagnostic::class);
    }

    /**
     * @return Diagnostic[]
     */
    public function findByUserAndKeyword(int $userId, ?string $keyword = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.user = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('d.date_scan', 'DESC');

        if ($keyword) {
            $qb->andWhere('d.resultat_ia LIKE :keyword')
                ->setParameter('keyword', '%' . $keyword . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Finds diagnostics within a specific radius (approximate) of a location.
     * Uses a bounding box for performance.
     * 
     * @return Diagnostic[]
     */
    public function findNearby(float $lat, float $lon, float $radiusKm = 25.0, int $days = 14): array
    {
        // 1 degree of latitude is ~111km
        // 1 degree of longitude at latitude L is ~111km * cos(L)
        // Tunisia is around 36 degrees lat, so cos(36) is ~0.81
        $latDelta = $radiusKm / 111.0;
        $lonDelta = $radiusKm / (111.0 * cos(deg2rad($lat)));

        // Include diagnostics that are either:
        // 1. Within the geographic bounding box, OR
        // 2. Have NULL coordinates (older records before location was added)
        return $this->createQueryBuilder('d')
            ->where('(d.latitude BETWEEN :latMin AND :latMax AND d.longitude BETWEEN :lonMin AND :lonMax) OR (d.latitude IS NULL OR d.longitude IS NULL)')
            ->andWhere('d.resultat_ia IS NOT NULL')
            ->setParameter('latMin', $lat - $latDelta)
            ->setParameter('latMax', $lat + $latDelta)
            ->setParameter('lonMin', $lon - $lonDelta)
            ->setParameter('lonMax', $lon + $lonDelta)
            ->orderBy('d.date_scan', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Diagnostic[]
     */
    public function findWithLocation(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.resultat_ia IS NOT NULL')
            ->orderBy('d.date_scan', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
