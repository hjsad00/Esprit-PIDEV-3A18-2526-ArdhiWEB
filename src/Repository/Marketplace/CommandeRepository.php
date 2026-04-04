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
}
