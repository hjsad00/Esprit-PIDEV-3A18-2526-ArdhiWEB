<?php

namespace App\Repository\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Parcelle>
 */
class ParcelleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Parcelle::class);
    }

    public function findByAgriculteur(User $user)
    {
        // JOIN FETCH prevents N+1 queries on lazy-loaded associations
        return $this->createQueryBuilder('p')
            ->leftJoin('p.agriculteur', 'a')
            ->addSelect('a')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $user)
            ->orderBy('p.created_at', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByAgriculteur(User $user): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getSurfaceTotalByAgriculteur(User $user): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.surface) as total_surface')
            ->andWhere('p.agriculteur = :agriculteur')
            ->setParameter('agriculteur', $user)
            ->getQuery()
            ->getOneOrNullResult();

        return (float) ($result['total_surface'] ?? 0);
    }

    public function getStatsByAgriculteur(User $user): array
    {
        return [
            'total_parcelles' => $this->countByAgriculteur($user),
            'surface_totale' => $this->getSurfaceTotalByAgriculteur($user),
            'parcelles_actives' => $this->createQueryBuilder('p')
                ->select('COUNT(p.id)')
                ->andWhere('p.agriculteur = :agriculteur')
                ->andWhere('p.statut = :statut')
                ->setParameter('agriculteur', $user)
                ->setParameter('statut', 'active')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    public function searchAndFilter(?User $user = null, ?string $query = null, ?string $typeSol = null, ?string $localisation = null)
    {
        $qb = $this->createQueryBuilder('p');

        if ($user) {
            $qb->andWhere('p.agriculteur = :user')
               ->setParameter('user', $user);
        }

        if ($query) {
            $orX = $qb->expr()->orX(
                $qb->expr()->like('p.localisation', ':q'),
                $qb->expr()->like('p.type_sol', ':q'),
                $qb->expr()->like('p.systeme_irrigation', ':q')
            );
            
            if (is_numeric($query)) {
                $orX->add($qb->expr()->eq('p.id', ':id'));
                $qb->setParameter('id', (int)$query);
            }
            
            $qb->andWhere($orX)
               ->setParameter('q', '%' . $query . '%');
        }

        if ($typeSol) {
            $qb->andWhere('p.type_sol = :typeSol')
               ->setParameter('typeSol', $typeSol);
        }

        if ($localisation) {
            $qb->andWhere('p.localisation = :loc')
               ->setParameter('loc', $localisation);
        }

        return $qb->orderBy('p.created_at', 'DESC')->getQuery();
    }
}
