<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Produits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produits>
 *
 * @method Produits|null find($id, $lockMode = null, $lockVersion = null)
 * @method Produits|null findOneBy(array $criteria, array $orderBy = null)
 * @method Produits[]    findAll()
 * @method Produits[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProduitsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produits::class);
    }

    /**
     * Rechercher des produits par catégorie.
     */
    public function findByCategorie(string $categorie): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.categorie = :cat')
            ->andWhere('p.visible = :true')
            ->andWhere('p.visibleAdmin = :true')
            ->setParameter('cat', $categorie)
            ->setParameter('true', true)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Rechercher des produits par mot-clé (nom ou description).
     */
    public function searchByKeyword(string $keyword): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('(p.nom LIKE :kw OR p.description LIKE :kw)')
            ->andWhere('p.visible = :true')
            ->andWhere('p.visibleAdmin = :true')
            ->setParameter('kw', '%' . $keyword . '%')
            ->setParameter('true', true)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Produits triés par prix (croissant ou décroissant).
     */
    public function findAllOrderedByPrice(string $direction = 'ASC'): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.visible = :true')
            ->andWhere('p.visibleAdmin = :true')
            ->setParameter('true', true)
            ->orderBy('p.prix', $direction)
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les produits sauf ceux de l'utilisateur connecté.
     */
    public function findAllExceptUser(?int $userId): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.visible = :true')
            ->andWhere('p.visibleAdmin = :true')
            ->setParameter('true', true);

        if ($userId) {
            $qb->andWhere('p.user != :uid')
               ->setParameter('uid', $userId);
        }

        return $qb->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les produits d'un utilisateur donné.
     */
    public function findByUser(int $userId): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :uid')
            ->setParameter('uid', $userId)
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste des catégories distinctes existantes en base.
     */
    public function findDistinctCategories(): array
    {
        return $this->createQueryBuilder('p')
            ->select('DISTINCT p.categorie')
            ->where('p.categorie IS NOT NULL')
            ->andWhere('p.categorie != :empty')
            ->setParameter('empty', '')
            ->orderBy('p.categorie', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
    }
}
