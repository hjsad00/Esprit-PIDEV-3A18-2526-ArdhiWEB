<?php

namespace App\Service\Marketplace;

use App\Entity\Marketplace\Produits;
use Doctrine\ORM\EntityManagerInterface;

class ProduitManager
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function creerProduit(Produits $produit): void
    {
        $this->entityManager->persist($produit);
        $this->entityManager->flush();
    }

    public function modifierProduit(Produits $produit): void
    {
        $this->entityManager->flush();
    }

    public function supprimerProduit(Produits $produit): void
    {
        $this->entityManager->remove($produit);
        $this->entityManager->flush();
    }

    public function diminuerStock(Produits $produit, int $quantite): bool
    {
        if ($produit->getQuantiteStock() < $quantite) {
            throw new \LogicException('Stock insuffisant pour ce produit.');
        }

        $produit->setQuantiteStock($produit->getQuantiteStock() - $quantite);
        $this->entityManager->flush();

        return true;
    }

    public function ajouterStock(Produits $produit, int $quantite): void
    {
        $produit->setQuantiteStock($produit->getQuantiteStock() + $quantite);
        $this->entityManager->flush();
    }
}
