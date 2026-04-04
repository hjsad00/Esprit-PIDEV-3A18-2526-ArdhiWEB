<?php

namespace App\Entity\Marketplace;

use App\Entity\UserAndDiag\User;
use App\Repository\Marketplace\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commande')]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCommande')]
    private ?int $id = null;

    #[ORM\Column(name: 'dateCommande', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateCommande = null;

    #[ORM\Column(length: 50, options: ['default' => 'en_cours'])]
    private ?string $etat = 'en_cours';

    #[ORM\Column(type: Types::FLOAT, options: ['default' => 0])]
    private float $total = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** @var Collection<int, DetailsCommande> */
    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: DetailsCommande::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $details;

    // ==================== CONSTRUCTOR ====================

    public function __construct()
    {
        $this->dateCommande = new \DateTime();
        $this->details = new ArrayCollection();
    }

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateCommande(): ?\DateTimeInterface
    {
        return $this->dateCommande;
    }

    public function setDateCommande(\DateTimeInterface $dateCommande): static
    {
        $this->dateCommande = $dateCommande;
        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(string $etat): static
    {
        $this->etat = $etat;
        return $this;
    }

    public function getTotal(): float
    {
        return $this->total;
    }

    public function setTotal(float $total): static
    {
        $this->total = $total;
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
     * @return Collection<int, DetailsCommande>
     */
    public function getDetails(): Collection
    {
        return $this->details;
    }

    public function addDetail(DetailsCommande $detail): static
    {
        if (!$this->details->contains($detail)) {
            $this->details->add($detail);
            $detail->setCommande($this);
        }
        return $this;
    }

    public function removeDetail(DetailsCommande $detail): static
    {
        if ($this->details->removeElement($detail)) {
            if ($detail->getCommande() === $this) {
                $detail->setCommande(null);
            }
        }
        return $this;
    }

    // ==================== HELPER METHODS ====================

    /**
     * Recalcule le total de la commande à partir de ses détails.
     */
    public function recalculerTotal(): void
    {
        $montant = 0.0;
        foreach ($this->details as $detail) {
            $montant += $detail->getPrixUnitaire() * $detail->getQuantite();
        }
        $this->total = round($montant, 2);
    }

    /**
     * Nombre total d'articles dans la commande.
     */
    public function getNombreArticles(): int
    {
        $count = 0;
        foreach ($this->details as $detail) {
            $count += $detail->getQuantite();
        }
        return $count;
    }
}
