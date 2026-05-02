<?php

namespace App\Entity\Marketplace;

use App\Entity\UserAndDiag\User;
use App\Repository\Marketplace\AvisRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité représentant un avis laissé par un utilisateur sur un produit.
 */
#[ORM\Entity(repositoryClass: AvisRepository::class)]
#[ORM\Table(name: 'avis')]
class Avis
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idAvis')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire.')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Produits::class)]
    #[ORM\JoinColumn(name: 'id_produit', referencedColumnName: 'idProduit', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le produit est obligatoire.')]
    private ?Produits $produit = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'La note est obligatoire.')]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note doit être comprise entre {{ min }} et {{ max }}.'
    )]
    private int $note;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(name: 'dateAvis', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeInterface $dateAvis;

    #[ORM\Column(name: 'isVerifiedBuyer', type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isVerifiedBuyer = false;

    public function __construct()
    {
        $this->dateAvis = new \DateTime();
    }

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
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

    public function getProduit(): ?Produits
    {
        return $this->produit;
    }

    public function setProduit(?Produits $produit): static
    {
        $this->produit = $produit;
        return $this;
    }

    public function getNote(): int
    {
        return $this->note;
    }

    public function setNote(int $note): static
    {
        $this->note = $note;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getDateAvis(): \DateTimeInterface
    {
        return $this->dateAvis;
    }

    public function setDateAvis(\DateTimeInterface $dateAvis): static
    {
        $this->dateAvis = $dateAvis;
        return $this;
    }

    public function isVerifiedBuyer(): bool
    {
        return $this->isVerifiedBuyer;
    }

    public function setIsVerifiedBuyer(bool $isVerifiedBuyer): static
    {
        $this->isVerifiedBuyer = $isVerifiedBuyer;
        return $this;
    }
}
