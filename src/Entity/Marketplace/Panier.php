<?php

namespace App\Entity\Marketplace;

use App\Entity\UserAndDiag\User;
use App\Repository\Marketplace\PanierRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PanierRepository::class)]
#[ORM\Table(name: 'panier')]
class Panier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idPanier')]
    private ?int $id = null;

    #[ORM\Column(name: 'dateCreation', type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'totalMontant', type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    private string $totalMontant = '0.00';

    #[ORM\Column(name: 'totalProduits', options: ['default' => 0])]
    private int $totalProduits = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** @var Collection<int, PanierProduit> */
    #[ORM\OneToMany(mappedBy: 'panier', targetEntity: PanierProduit::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $panierProduits;

    // ==================== CONSTRUCTOR ====================

    public function __construct()
    {
        $this->dateCreation  = new \DateTime();
        $this->panierProduits = new ArrayCollection();
    }

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateCreation(): \DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;
        return $this;
    }

    public function getTotalMontant(): float
    {
        return (float) $this->totalMontant;
    }

    public function setTotalMontant(float $totalMontant): static
    {
        $this->totalMontant = number_format($totalMontant, 2, '.', '');
        return $this;
    }

    public function getTotalProduits(): int
    {
        return $this->totalProduits;
    }

    public function setTotalProduits(int $totalProduits): static
    {
        $this->totalProduits = $totalProduits;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    /**
     * @return Collection<int, PanierProduit>
     */
    public function getPanierProduits(): Collection
    {
        return $this->panierProduits;
    }

    public function addPanierProduit(PanierProduit $panierProduit): static
    {
        if (!$this->panierProduits->contains($panierProduit)) {
            $this->panierProduits->add($panierProduit);
            $panierProduit->setPanier($this);
        }
        return $this;
    }

    public function removePanierProduit(PanierProduit $panierProduit): static
    {
        if ($this->panierProduits->removeElement($panierProduit)) {
            if ($panierProduit->getPanier() === $this) {
                $panierProduit->setPanier(null);
            }
        }
        return $this;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Recalcule totalMontant et totalProduits à partir des lignes du panier.
     */
    public function recalculer(): void
    {
        $montant  = 0.0;
        $quantite = 0;

        foreach ($this->panierProduits as $ligne) {
            $produit = $ligne->getProduit();
            if ($produit === null) {
                continue;
            }

            $montant  += $produit->getPrixFinal() * $ligne->getQuantite();
            $quantite += $ligne->getQuantite();
        }

        $this->setTotalMontant(round($montant, 2));
        $this->totalProduits = $quantite;
    }
    /**
 * Retourne le nombre de vendeurs uniques dans le panier.
 * Utilisé pour calculer les frais de livraison dynamiques (7 DT x vendeur).
 */
public function getNombreVendeurs(): int
{
    $vendeurIds = [];

    foreach ($this->panierProduits as $ligne) {
        $produit = $ligne->getProduit();
        if ($produit === null) {
            continue;
        }

        $vendeur = $produit->getUser();
        if ($vendeur !== null) {
            $vendeurIds[$vendeur->getId()] = true;
        }
    }

    return count($vendeurIds);
}
}
