<?php

namespace App\Entity\Marketplace;

use App\Entity\UserAndDiag\User;
use App\Entity\Marketplace\Coupon;
use App\Repository\Marketplace\CommandeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commande')]
#[ORM\HasLifecycleCallbacks]
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

    #[ORM\Column(name: 'frais_livraison', type: Types::FLOAT, options: ['default' => 0])]
    private float $fraisLivraison = 0;

    #[ORM\Column(name: 'mode_livraison', length: 50, nullable: true)]
    private ?string $modeLivraison = null;

    #[ORM\Column(name: 'payee_par_points', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $payeeParPoints = false;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    /** @var Collection<int, DetailsCommande> */
    #[ORM\OneToMany(mappedBy: 'commande', targetEntity: DetailsCommande::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $details;

    #[ORM\ManyToOne(targetEntity: Coupon::class)]
    #[ORM\JoinColumn(name: 'idCoupon', referencedColumnName: 'idCoupon', nullable: true)]
    private ?Coupon $coupon = null;

    #[ORM\Column(name: 'montantRemise', type: Types::FLOAT, options: ['default' => 0])]
    private float $montantRemise = 0;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $qrCodeToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qrCodePath = null;

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

    public function getFraisLivraison(): float
    {
        return $this->fraisLivraison;
    }

    public function setFraisLivraison(float $fraisLivraison): static
    {
        $this->fraisLivraison = $fraisLivraison;
        return $this;
    }

    public function getModeLivraison(): ?string
    {
        return $this->modeLivraison;
    }

    public function setModeLivraison(?string $modeLivraison): static
    {
        $this->modeLivraison = $modeLivraison;
        return $this;
    }

    public function isPayeeParPoints(): bool
    {
        return $this->payeeParPoints;
    }

    public function setPayeeParPoints(bool $payeeParPoints): static
    {
        $this->payeeParPoints = $payeeParPoints;
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

    public function getCoupon(): ?Coupon
    {
        return $this->coupon;
    }

    public function setCoupon(?Coupon $coupon): static
    {
        $this->coupon = $coupon;
        return $this;
    }

    public function getMontantRemise(): float
    {
        return $this->montantRemise;
    }

    public function setMontantRemise(float $montantRemise): static
    {
        $this->montantRemise = $montantRemise;
        return $this;
    }

    public function getQrCodeToken(): ?string
    {
        return $this->qrCodeToken;
    }

    public function setQrCodeToken(?string $qrCodeToken): self
    {
        $this->qrCodeToken = $qrCodeToken;
        return $this;
    }

    public function getQrCodePath(): ?string
    {
        return $this->qrCodePath;
    }

    public function setQrCodePath(?string $qrCodePath): self
    {
        $this->qrCodePath = $qrCodePath;
        return $this;
    }

    #[ORM\PrePersist]
    public function generateToken(): void
    {
        if (null === $this->qrCodeToken) {
            $this->qrCodeToken = bin2hex(random_bytes(16));
        }
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
