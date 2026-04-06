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
            ->setParameter('cat', $categorie)
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
            ->andWhere('p.nom LIKE :kw OR p.description LIKE :kw')
            ->setParameter('kw', '%' . $keyword . '%')
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
            ->orderBy('p.prix', $direction)
            ->getQuery()
            ->getResult();
    }
}
