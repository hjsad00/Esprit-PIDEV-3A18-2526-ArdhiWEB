<?php

namespace App\Entity\UserAndDiag;

use App\Repository\UserAndDiag\UserBlockRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserBlockRepository::class)]
#[ORM\Table(name: 'user_block')]
class UserBlock
{
    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'userBlocks')]
    #[ORM\JoinColumn(name: 'user_source', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $blocker = null;

    #[ORM\Id]
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_target', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $blocked = null;

    public function getBlocker(): ?User
    {
        return $this->blocker;
    }

    public function setBlocker(?User $blocker): static
    {
        $this->blocker = $blocker;
        return $this;
    }

    public function getBlocked(): ?User
    {
        return $this->blocked;
    }

    public function setBlocked(?User $blocked): static
    {
        $this->blocked = $blocked;
        return $this;
    }
}
