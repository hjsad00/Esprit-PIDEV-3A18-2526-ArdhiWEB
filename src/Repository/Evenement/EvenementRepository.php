<?php

namespace App\Repository\Evenement;

use App\Entity\Evenement\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    public function findWithFilters(?string $type, ?string $statut, ?string $search): array
    {
        $qb = $this->createQueryBuilder('e')->orderBy('e.dateDebut', 'ASC');
        if ($type) { $qb->andWhere('e.type = :type')->setParameter('type', $type); }
        if ($statut) { $qb->andWhere('e.statut = :statut')->setParameter('statut', $statut); }
        if ($search) { $qb->andWhere('e.titre LIKE :search OR e.lieu LIKE :search OR e.organisateur LIKE :search')->setParameter('search', '%' . $search . '%'); }
        return $qb->getQuery()->getResult();
    }
}
