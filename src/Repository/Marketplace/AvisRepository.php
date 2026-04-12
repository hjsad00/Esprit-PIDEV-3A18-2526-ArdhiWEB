<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Avis;
use App\Entity\Marketplace\Produits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Avis>
 */
class AvisRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Avis::class);
    }

    /**
     * Récupère tous les avis pour un produit donné, triés par date décroissante.
     */
    public function findByProduit(int $produitId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.produit = :id')
            ->setParameter('id', $produitId)
            ->orderBy('a.dateAvis', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule la note moyenne pour un produit.
     */
    public function getAverageNote(int $produitId): float
    {
        $result = $this->createQueryBuilder('a')
            ->select('AVG(a.note)')
            ->andWhere('a.produit = :id')
            ->setParameter('id', $produitId)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? round((float)$result, 1) : 0.0;
    }

    /**
     * Récupère les avis d'un produit avec l'auteur préchargé.
     */
    public function findByProduitWithUser(Produits $produit): array
    {
        return $this->createQueryBuilder('a')
            ->leftJoin('a.user', 'u')
            ->addSelect('u')
            ->andWhere('a.produit = :produit')
            ->setParameter('produit', $produit)
            ->orderBy('a.dateAvis', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les stats [produitId => ['avg' => float, 'count' => int]] pour une liste de produits.
     *
     * @param Produits[] $produits
     * @return array<int, array{avg: float, count: int}>
     */
    public function getStatsForProduits(array $produits): array
    {
        if ($produits === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('a')
            ->select('IDENTITY(a.produit) AS produitId, AVG(a.note) AS avgNote, COUNT(a.id) AS reviewsCount')
            ->andWhere('a.produit IN (:produits)')
            ->setParameter('produits', $produits)
            ->groupBy('a.produit')
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $productId = (int) $row['produitId'];
            $stats[$productId] = [
                'avg' => round((float) $row['avgNote'], 1),
                'count' => (int) $row['reviewsCount'],
            ];
        }

        return $stats;
    }
}
