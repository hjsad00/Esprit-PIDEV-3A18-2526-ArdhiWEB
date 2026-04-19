<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\ModerationAuditRepository::class)]
#[ORM\Table(name: "moderation_audit")]
class ModerationAudit
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'moderator_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $moderator = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'target_user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $targetUser = null;

    #[ORM\Column(length: 50)]
    private ?string $action = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(nullable: true)]
    private ?int $related_post_id = null;

    #[ORM\Column(nullable: true)]
    private ?int $related_comment_id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getModerator(): ?User
    {
        return $this->moderator;
    }
    public function setModerator(?User $moderator): static
    {
        $this->moderator = $moderator;
        return $this;
    }

    public function getTargetUser(): ?User
    {
        return $this->targetUser;
    }
    public function setTargetUser(?User $targetUser): static
    {
        $this->targetUser = $targetUser;
        return $this;
    }

    public function getAction(): ?string
    {
        return $this->action;
    }
    public function setAction(string $action): static
    {
        $this->action = $action;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }
    public function setReason(?string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function getRelatedPostId(): ?int
    {
        return $this->related_post_id;
    }
    public function setRelatedPostId(?int $related_post_id): static
    {
        $this->related_post_id = $related_post_id;
        return $this;
    }

    public function getRelatedCommentId(): ?int
    {
        return $this->related_comment_id;
    }
    public function setRelatedCommentId(?int $related_comment_id): static
    {
        $this->related_comment_id = $related_comment_id;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at;
    }
    public function setCreatedAt(?\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }
}
