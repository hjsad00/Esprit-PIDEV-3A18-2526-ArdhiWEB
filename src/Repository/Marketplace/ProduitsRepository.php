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
            ->andWhere('p.quantiteStock > 0')
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
            ->andWhere('p.quantiteStock > 0')
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
            ->andWhere('p.quantiteStock > 0')
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
            ->andWhere('p.quantiteStock > 0')
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

    /**
     * Filtrage avancé du catalogue avec tous les critères combinés.
     */
    public function findAllWithFilters(array $filters, ?int $excludeUserId, bool $isAdmin = false): array
    {
        $qb = $this->createQueryBuilder('p');

        if (!$isAdmin) {
            $qb->andWhere('p.visible = :true')
               ->andWhere('p.visibleAdmin = :true')
             ->andWhere('p.quantiteStock > 0')
               ->setParameter('true', true);
        }

        if ($excludeUserId) {
            $qb->andWhere('p.user != :uid')->setParameter('uid', $excludeUserId);
        }

        if (!empty($filters['nom'])) {
            $qb->andWhere('p.nom LIKE :nom')
               ->setParameter('nom', '%' . $filters['nom'] . '%');
        }

        if (!empty($filters['categorie'])) {
            $qb->andWhere('p.categorie = :cat')
               ->setParameter('cat', $filters['categorie']);
        }

        // --- FILTRAGE SPATIAL ---
        if (isset($filters['valid_cities'])) {
            // Nous joignons le vendeur 'v' si ce n'est pas déjà fait plus tard.
            // On s'assure de l'ajouter ici pour la clause WHERE.
            if (!in_array('v', $qb->getAllAliases())) {
                $qb->join('p.user', 'v');
            }
            $qb->andWhere('LOWER(v.location) IN (:villes)')
               ->setParameter('villes', $filters['valid_cities']);
        }

        if (isset($filters['prix_min']) && $filters['prix_min'] !== '') {
            $qb->andWhere('p.prix >= :pmin')
               ->setParameter('pmin', (float) $filters['prix_min']);
        }

        if (isset($filters['prix_max']) && $filters['prix_max'] !== '') {
            $qb->andWhere('p.prix <= :pmax')
               ->setParameter('pmax', (float) $filters['prix_max']);
        }

        if (isset($filters['stock_min']) && $filters['stock_min'] !== '') {
            $qb->andWhere('p.quantiteStock >= :smin')
               ->setParameter('smin', (int) $filters['stock_min']);
        }

        if (isset($filters['stock_max']) && $filters['stock_max'] !== '') {
            $qb->andWhere('p.quantiteStock <= :smax')
               ->setParameter('smax', (int) $filters['stock_max']);
        }

        if (!empty($filters['en_solde'])) {
            $qb->andWhere('p.remise > 0');
        }

        if ($isAdmin && isset($filters['visible']) && $filters['visible'] !== 'ALL') {
            $qb->andWhere('p.visible = :vis')
               ->setParameter('vis', (bool)$filters['visible']);
        }

        if ($isAdmin && isset($filters['admin']) && $filters['admin'] !== 'ALL') {
            $qb->andWhere('p.visibleAdmin = :adm')
               ->setParameter('adm', (bool)$filters['admin']);
        }

        if ($isAdmin && !empty($filters['vendeur'])) {
            if (!in_array('v', $qb->getAllAliases())) {
                $qb->join('p.user', 'v');
            }
            $qb->andWhere('(v.nom LIKE :vdr OR v.prenom LIKE :vdr OR v.email LIKE :vdr)')
               ->setParameter('vdr', '%' . $filters['vendeur'] . '%');
        }

        // Tri
        $tri = $filters['tri'] ?? 'recent';
        match($tri) {
            'prix_asc'   => $qb->orderBy('p.prix', 'ASC'),
            'prix_desc'  => $qb->orderBy('p.prix', 'DESC'),
            'nom_az'     => $qb->orderBy('p.nom', 'ASC'),
            'nom_za'     => $qb->orderBy('p.nom', 'DESC'),
            'solde'      => $qb->orderBy('p.remise', 'DESC')->addOrderBy('p.id', 'DESC'),
            default      => $qb->orderBy('p.id', 'DESC'),
        };

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupère le prix minimum et maximum de tous les produits visibles.
     */
    public function findPriceRange(?int $excludeUserId, bool $isAdmin = false): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('MIN(p.prix) as pMin, MAX(p.prix) as pMax');

        if (!$isAdmin) {
            $qb->andWhere('p.visible = :true')
               ->andWhere('p.visibleAdmin = :true')
             ->andWhere('p.quantiteStock > 0')
               ->setParameter('true', true);
        }

        if ($excludeUserId) {
            $qb->andWhere('p.user != :uid')->setParameter('uid', $excludeUserId);
        }

        return $qb->getQuery()->getSingleResult();
    }
}
