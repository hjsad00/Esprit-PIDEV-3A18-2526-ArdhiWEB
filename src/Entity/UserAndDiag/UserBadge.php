<?php

namespace App\Entity\UserAndDiag;

use App\Repository\UserAndDiag\UserBadgeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\UserAndDiag\User;

#[ORM\Entity(repositoryClass: UserBadgeRepository::class)]
#[ORM\Table(name: 'user_badge')]
class UserBadge
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: Badge::class)]
    #[ORM\JoinColumn(name: 'badge_id', referencedColumnName: 'id', nullable: false)]
    private ?Badge $badge = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $acquired_at = null;

    public function __construct()
    {
        $this->acquired_at = new \DateTime();
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

    public function getBadge(): ?Badge
    {
        return $this->badge;
    }

    public function setBadge(?Badge $badge): static
    {
        $this->badge = $badge;
        return $this;
    }

    public function getAcquiredAt(): ?\DateTimeInterface
    {
        return $this->acquired_at;
    }

    public function setAcquiredAt(?\DateTimeInterface $acquired_at): static
    {
        $this->acquired_at = $acquired_at;
        return $this;
    }

    public function getComputedId(): int
    {
        return ($this->user ? $this->user->getId() : 0) * 10000 + ($this->badge ? $this->badge->getId() : 0);
    }
}
