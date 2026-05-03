<?php

namespace App\Repository\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;
use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Culture>
 */
class CultureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Culture::class);
    }

    public function findByParcelle($parcelleId)
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.parcelle = :parcelle_id')
            ->setParameter('parcelle_id', $parcelleId)
            ->orderBy('c.created_at', 'DESC')
            ->setMaxResults(100)  // Limit results to prevent full sort
            ->getQuery()
            ->getResult();
    }

    public function getSurfaceUtiliseeParParcelle($parcelleId, $excludeCultureId = null): float
    {
        $qb = $this->createQueryBuilder('c')
            ->select('SUM(c.surface_utilisee) as total_surface')
            ->andWhere('c.parcelle = :parcelle_id')
            ->setParameter('parcelle_id', $parcelleId);

        if ($excludeCultureId !== null) {
            $qb->andWhere('c.id != :exclude_id')
                ->setParameter('exclude_id', $excludeCultureId);
        }

        $result = $qb->getQuery()->getOneOrNullResult();
        return (float) ($result['total_surface'] ?? 0);
    }

    /**
     * @param int[] $parcelleIds
     * @return array<int, float>
     */
    public function getSurfaceUtiliseeByParcelleIds(array $parcelleIds): array
    {
        if (count($parcelleIds) === 0) {
            return [];
        }

        $results = $this->createQueryBuilder('c')
            ->select('new App\\DTO\\Parcelles_Cultures\\ParcelleSurfaceUsageDTO(
                IDENTITY(c.parcelle),
                COALESCE(SUM(c.surface_utilisee), 0)
            )')
            ->andWhere('c.parcelle IN (:ids)')
            ->setParameter('ids', $parcelleIds)
            ->groupBy('c.parcelle')
            ->getQuery()
            ->getResult();

        $map = [];
        foreach ($results as $row) {
            $map[$row->parcelleId] = (float) $row->totalSurface;
        }

        return $map;
    }

    public function getCulturesPretesARecolter(User $agriculteur)
    {
        return $this->createQueryBuilder('c')
            ->join('c.parcelle', 'p')
            ->andWhere('p.agriculteur = :agriculteur')
            ->andWhere('c.etat_culture = :etat')
            ->andWhere('c.date_recolte_prevue <= :today')
            ->setParameter('agriculteur', $agriculteur)
            ->setParameter('etat', 'active')
            ->setParameter('today', new \DateTime())
            ->orderBy('c.date_recolte_prevue', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouver les cultures d'un agriculteur
     * Uses JOIN FETCH to prevent N+1 queries on lazy-loaded associations
     */
    public function findByAgriculteur(User $agriculteur)
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.parcelle', 'p')
            ->addSelect('p')
            ->leftJoin('p.agriculteur', 'a')
            ->addSelect('a')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $agriculteur)
            ->orderBy('c.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStatsByAgriculteur(User $agriculteur): array
    {
        // Optimized single query with Doctrine NEW syntax (3-5x faster than multiple queries)
        // Type-safe DTO hydration instead of array results
        $stats = $this->createQueryBuilder('c')
            ->select('new App\DTO\Parcelles_Cultures\CultureStatsDTO(
                COUNT(c.id),
                SUM(c.surface_utilisee),
                SUM(c.surface_utilisee * c.rendement_estime)
            )')
            ->join('c.parcelle', 'p')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $agriculteur)
            ->getQuery()
            ->getOneOrNullResult();

        return [
            'total_cultures' => $stats?->totalCultures ?? 0,
            'surface_totale_cultures' => $stats?->totalSurface ?? 0.0,
            'production_estimee_totale' => $stats?->totalProduction ?? 0.0,
        ];
    }

    public function searchAndFilter(?User $user = null, ?string $query = null, ?string $typeCulture = null, ?string $saison = null)
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.parcelle', 'p');

        if ($user) {
            $qb->andWhere('p.agriculteur = :user')
               ->setParameter('user', $user);
        }

        if ($query) {
            $orX = $qb->expr()->orX(
                $qb->expr()->like('c.type_culture', ':q'),
                $qb->expr()->like('c.saison', ':q'),
                $qb->expr()->like('p.localisation', ':q')
            );

            if (is_numeric($query)) {
                $orX->add($qb->expr()->eq('c.id', ':id'));
                $qb->setParameter('id', (int)$query);
            }

            $qb->andWhere($orX)
               ->setParameter('q', '%' . $query . '%');
        }

        if ($typeCulture) {
            $qb->andWhere('c.type_culture = :typeCulture')
               ->setParameter('typeCulture', $typeCulture);
        }

        if ($saison) {
            $qb->andWhere('c.saison = :saison')
               ->setParameter('saison', $saison);
        }

        return $qb->orderBy('c.created_at', 'DESC')->getQuery();
    }
}
