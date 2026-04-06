<?php

namespace App\Entity\Marketplace;

use App\Entity\UserAndDiag\User;
use App\Repository\Marketplace\CouponUtilisationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CouponUtilisationRepository::class)]
#[ORM\Table(name: 'coupon_utilisation')]
#[ORM\UniqueConstraint(name: 'unique_coupon_user', columns: ['idCoupon', 'idUser'])]
class CouponUtilisation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Coupon::class)]
    #[ORM\JoinColumn(name: 'idCoupon', referencedColumnName: 'idCoupon', nullable: false, onDelete: 'CASCADE')]
    private ?Coupon $coupon = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'idUser', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(name: 'nombreUtilisation', options: ['default' => 0])]
    private ?int $nombreUtilisation = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getNombreUtilisation(): ?int
    {
        return $this->nombreUtilisation;
    }

    public function setNombreUtilisation(int $nombreUtilisation): static
    {
        $this->nombreUtilisation = $nombreUtilisation;
        return $this;
    }
}
