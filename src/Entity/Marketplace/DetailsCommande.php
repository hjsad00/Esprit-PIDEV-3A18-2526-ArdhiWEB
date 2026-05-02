<?php

namespace App\Entity\Marketplace;

use App\Repository\Marketplace\DetailsCommandeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DetailsCommandeRepository::class)]
#[ORM\Table(name: 'detailscommande')]
class DetailsCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idDetails')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Commande::class, inversedBy: 'details')]
    #[ORM\JoinColumn(name: 'id_commande', referencedColumnName: 'idCommande', nullable: false, onDelete: 'CASCADE')]
    private ?Commande $commande = null;

    #[ORM\ManyToOne(targetEntity: Produits::class)]
    #[ORM\JoinColumn(name: 'id_produit', referencedColumnName: 'idProduit', nullable: false, onDelete: 'CASCADE')]
    private ?Produits $produit = null;

    #[ORM\Column(options: ['default' => 1])]
    private int $quantite = 1;

    #[ORM\Column(name: 'prixUnitaire', type: Types::FLOAT)]
    private float $prixUnitaire;

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;
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

    public function getPrixUnitaire(): float
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(float $prixUnitaire): static
    {
        $this->prixUnitaire = $prixUnitaire;
        return $this;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Sous-total de la ligne (prix unitaire × quantité).
     */
    public function getSousTotal(): float
    {
        return round($this->prixUnitaire * $this->quantite, 2);
    }
}
