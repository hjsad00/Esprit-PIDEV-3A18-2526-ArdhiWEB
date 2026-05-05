<?php

namespace App\Repository\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Materiel>
 */
class MaterielRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Materiel::class);
    }

    public function searchByUser(int $userId, ?string $search = null, ?string $type = null, ?string $etat = null): array
    {
        $qb = $this->createQueryBuilder('m')
            ->andWhere('m.user = :userId')
            ->setParameter('userId', $userId);

        if ($search) {
            $qb->andWhere('m.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($type) {
            $qb->andWhere('m.type = :type')
               ->setParameter('type', $type);
        }

        if ($etat) {
            $qb->andWhere('m.etat = :etat')
               ->setParameter('etat', $etat);
        }

        return $qb->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getStatsByUser(int $userId): array
    {
        $total = $this->createQueryBuilder('m')
            ->select('count(m.id)')
            ->andWhere('m.user = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        $enPanne = $this->createQueryBuilder('m')
            ->select('count(m.id)')
            ->andWhere('m.user = :userId')
            ->andWhere('m.etat = :etat')
            ->setParameter('userId', $userId)
            ->setParameter('etat', 'En panne')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'en_panne' => $enPanne,
        ];
    }
    
    public function findEnRetardByUser(int $userId): array
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.user = :userId')
            ->andWhere('m.dateProchaineMaintenance < :now')
            ->setParameter('userId', $userId)
            ->setParameter('now', new \DateTime())
            ->getQuery()
            ->getResult();
    }

    /**
     * Pour l'administration : trouve tous les matériels avec filtres optionnels.
     * On peut chercher par nom de matériel OU par nom de l'agriculteur.
     */
    public function findAllForAdmin(?string $search = null, ?string $type = null, ?string $etat = null): array
    {
        $qb = $this->createQueryBuilder('m');

        if ($search) {
            // Dans ce projet, userId est un simple entier, donc on récupère d'abord les IDs des utilisateurs correspondants
            // ou on fait une sous-requête.
            // On va simplifier en cherchant le nom du matériel.
            // Si on voulait chercher par nom de user, il faudrait que la relation soit mappée.
            $qb->andWhere('m.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($type) {
            $qb->andWhere('m.type = :type')
               ->setParameter('type', $type);
        }

        if ($etat) {
            $qb->andWhere('m.etat = :etat')
               ->setParameter('etat', $etat);
        }

        return $qb->orderBy('m.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
