<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\CommunityReportRepository::class)]
#[ORM\Table(name: "community_report")]
class CommunityReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'reporter_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $reporter = null;

    #[ORM\ManyToOne(targetEntity: CommunityPost::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?CommunityPost $post = null;

    #[ORM\ManyToOne(targetEntity: CommunityComment::class)]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?CommunityComment $comment = null;

    #[ORM\Column(length: 255)]
    private string $reason;

    #[ORM\Column(options: ["default" => false])]
    private bool $is_resolved = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $created_at;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReporter(): ?User
    {
        return $this->reporter;
    }
    public function setReporter(?User $reporter): static
    {
        $this->reporter = $reporter;
        return $this;
    }

    public function getPost(): ?CommunityPost
    {
        return $this->post;
    }
    public function setPost(?CommunityPost $post): static
    {
        $this->post = $post;
        return $this;
    }

    public function getComment(): ?CommunityComment
    {
        return $this->comment;
    }
    public function setComment(?CommunityComment $comment): static
    {
        $this->comment = $comment;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason ?? null;
    }
    public function setReason(string $reason): static
    {
        $this->reason = $reason;
        return $this;
    }

    public function isResolved(): ?bool
    {
        return $this->is_resolved ?? false;
    }
    public function setIsResolved(bool $is_resolved): static
    {
        $this->is_resolved = $is_resolved;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->created_at ?? null;
    }
    public function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }
}
