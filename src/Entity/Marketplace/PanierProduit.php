<?php

namespace App\Entity\Marketplace;

use App\Repository\Marketplace\PanierProduitRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Ligne de panier : association entre un Panier et un Produit.
 * Clé primaire composite (idPanier, idProduit) — identique au schéma SQL.
 */
#[ORM\Entity(repositoryClass: PanierProduitRepository::class)]
#[ORM\Table(name: 'panier_produits')]
class PanierProduit
{
    /**
     * Clé primaire composite côté Doctrine.
     * On utilise un surrogate PK auto-incrémenté pour simplifier la gestion ORM,
     * la contrainte UNIQUE (idPanier, idProduit) garantit l'unicité métier.
     */
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Panier::class, inversedBy: 'panierProduits')]
    #[ORM\JoinColumn(name: 'idPanier', referencedColumnName: 'idPanier', nullable: false, onDelete: 'CASCADE')]
    private ?Panier $panier = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Produits::class)]
    #[ORM\JoinColumn(name: 'idProduit', referencedColumnName: 'idProduit', nullable: false, onDelete: 'CASCADE')]
    private ?Produits $produit = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $quantite = 1;

    // ==================== GETTERS & SETTERS ====================


    public function getPanier(): ?Panier
    {
        return $this->panier;
    }

    public function setPanier(?Panier $panier): static
    {
        $this->panier = $panier;
        return $this;
    }

    public function getProduit(): ?Produits
    {
        return $this->produit;
    }

    public function setProduit(?Produits $produit): static
    {
        $this->produit = $produit;
        return $this;
    }

    public function getQuantite(): int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = max(1, $quantite);
        return $this;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Sous-total de la ligne (prix final × quantité).
     */
    public function getSousTotal(): float
    {
        return round($this->produit->getPrixFinal() * $this->quantite, 2);
    }
}
