<?php

namespace App\Repository\Marketplace;

use App\Entity\Marketplace\Panier;
use App\Entity\Marketplace\PanierProduit;
use App\Entity\Marketplace\Produits;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PanierProduit>
 *
 * @method PanierProduit|null find($id, $lockMode = null, $lockVersion = null)
 * @method PanierProduit|null findOneBy(array $criteria, array $orderBy = null)
 * @method PanierProduit[]    findAll()
 * @method PanierProduit[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PanierProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PanierProduit::class);
    }

    /**
     * Cherche une ligne de panier pour un panier et un produit donnés.
     */
    public function findLigne(Panier $panier, Produits $produit): ?PanierProduit
    {
        return $this->findOneBy([
            'panier'  => $panier,
            'produit' => $produit,
        ]);
    }

    /**
     * Ajoute un produit au panier ou incrémente sa quantité si déjà présent.
     * Recalcule le panier puis flush.
     */
    public function ajouterOuIncrementer(Panier $panier, Produits $produit, int $quantite = 1): PanierProduit
    {
        $ligne = $this->findLigne($panier, $produit);

        if ($ligne === null) {
            $ligne = new PanierProduit();
            $ligne->setPanier($panier);
            $ligne->setProduit($produit);
            $ligne->setQuantite($quantite);
            $panier->addPanierProduit($ligne);
            $this->getEntityManager()->persist($ligne);
        } else {
            $ligne->setQuantite($ligne->getQuantite() + $quantite);
        }

        $panier->recalculer();
        $this->getEntityManager()->flush();

        return $ligne;
    }

    /**
     * Modifie la quantité d'une ligne. Si quantité <= 0, supprime la ligne.
     */
    public function modifierQuantite(Panier $panier, Produits $produit, int $quantite): void
    {
        $ligne = $this->findLigne($panier, $produit);

        if ($ligne === null) {
            return;
        }

        $em = $this->getEntityManager();

        if ($quantite <= 0) {
            $panier->removePanierProduit($ligne);
            $em->remove($ligne);
        } else {
            $ligne->setQuantite($quantite);
        }

        $panier->recalculer();
        $em->flush();
    }

    /**
     * Supprime une ligne de panier (un produit du panier).
     */
    public function supprimerLigne(Panier $panier, Produits $produit): void
    {
        $this->modifierQuantite($panier, $produit, 0);
    }

    /**
     * Retourne toutes les lignes d'un panier avec les produits chargés.
     */
    public function findLignesByPanier(Panier $panier): array
    {
        return $this->createQueryBuilder('pp')
            ->leftJoin('pp.produit', 'p')
            ->addSelect('p')
            ->andWhere('pp.panier = :panier')
            ->setParameter('panier', $panier)
            ->orderBy('p.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
