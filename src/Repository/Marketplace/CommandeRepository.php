<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Commande;
use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commande>
 *
 * @method Commande|null find($id, $lockMode = null, $lockVersion = null)
 * @method Commande|null findOneBy(array $criteria, array $orderBy = null)
 * @method Commande[]    findAll()
 * @method Commande[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CommandeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commande::class);
    }

    /**
     * Récupère toutes les commandes d'un utilisateur, triées par date décroissante.
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->setParameter('user', $user)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère une commande avec tous ses détails (produits) en une seule requête.
     */
    public function findCommandeWithDetails(int $idCommande): ?Commande
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.details', 'd')
            ->addSelect('d')
            ->leftJoin('d.produit', 'p')
            ->addSelect('p')
            ->andWhere('c.id = :id')
            ->setParameter('id', $idCommande)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère les commandes contenant des produits d'un vendeur donné.
     */
    public function findOrdersBySeller(User $seller): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.details', 'd')
            ->innerJoin('d.produit', 'p')
            ->addSelect('d', 'p')
            ->andWhere('p.user = :seller')
            ->setParameter('seller', $seller)
            ->orderBy('c.dateCommande', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques acheteur : nombre de commandes et total dépensé.
     */
    public function getStatsForBuyer(User $user): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('COUNT(c.id) AS nbCommandes, COALESCE(SUM(c.total), 0) AS totalDepense')
            ->andWhere('c.user = :user')
            ->andWhere('c.etat != :annulee')
            ->setParameter('user', $user)
            ->setParameter('annulee', 'annulee')
            ->getQuery()
            ->getSingleResult();

        return [
            'nbCommandes'  => (int) $result['nbCommandes'],
            'totalDepense' => round((float) $result['totalDepense'], 2),
        ];
    }

    /**
     * Statistiques vendeur : nombre de commandes reçues et total gagné.
     */
    public function getStatsForSeller(User $seller): array
    {
        $result = $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id) AS nbCommandes, COALESCE(SUM(d.prixUnitaire * d.quantite), 0) AS totalGagne')
            ->innerJoin('c.details', 'd')
            ->innerJoin('d.produit', 'p')
            ->andWhere('p.user = :seller')
            ->andWhere('c.etat != :annulee')
            ->setParameter('seller', $seller)
            ->setParameter('annulee', 'annulee')
            ->getQuery()
            ->getSingleResult();

        return [
            'nbCommandes' => (int) $result['nbCommandes'],
            'totalGagne'  => round((float) $result['totalGagne'], 2),
        ];
    }
}
