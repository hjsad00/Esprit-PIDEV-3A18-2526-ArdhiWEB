<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Panier;
use App\Entity\UserAndDiag\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Panier>
 *
 * @method Panier|null find($id, $lockMode = null, $lockVersion = null)
 * @method Panier|null findOneBy(array $criteria, array $orderBy = null)
 * @method Panier[]    findAll()
 * @method Panier[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PanierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Panier::class);
    }

    /**
     * Récupère le panier actif d'un utilisateur (le plus récent).
     */
    public function findPanierActif(User $user): ?Panier
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->orderBy('p.dateCreation', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère le panier avec toutes ses lignes (produits) en une seule requête.
     */
    public function findPanierWithProduits(int $idPanier): ?Panier
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.panierProduits', 'pp')
            ->addSelect('pp')
            ->leftJoin('pp.produit', 'prod')
            ->addSelect('prod')
            ->andWhere('p.id = :id')
            ->setParameter('id', $idPanier)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère ou crée un panier pour l'utilisateur donné.
     */
    public function getOrCreatePanier(User $user): Panier
    {
        $panier = $this->findPanierActif($user);

        if ($panier === null) {
            $panier = new Panier();
            $panier->setUser($user);

            $em = $this->getEntityManager();
            $em->persist($panier);
            $em->flush();
        }

        return $panier;
    }

    /**
     * Supprime tous les paniers d'un utilisateur (utile pour le vidage complet).
     * Supprime d'abord les lignes panier_produits pour éviter la violation FK.
     */
    public function viderPanierUser(User $user): void
    {
        $em = $this->getEntityManager();

        // 1. Récupérer les IDs des paniers de l'utilisateur
        $panierIds = $this->createQueryBuilder('p')
            ->select('p.id')
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($panierIds)) {
            return;
        }

        // 2. Supprimer les lignes panier_produits liées
        $em->createQuery(
            'DELETE App\Entity\Marketplace\PanierProduit pp WHERE pp.panier IN (:ids)'
        )->setParameter('ids', $panierIds)->execute();

        // 3. Supprimer les paniers
        $this->createQueryBuilder('p')
            ->delete()
            ->andWhere('p.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}
