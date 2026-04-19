<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\UserAndDiag\User;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\CommunityLikeRepository::class)]
#[ORM\Table(name: "community_likes")]
#[ORM\UniqueConstraint(name: "unique_post_like", columns: ["user_id", "post_id"])]
#[ORM\UniqueConstraint(name: "unique_comment_like", columns: ["user_id", "comment_id"])]
class CommunityLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: CommunityPost::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?CommunityPost $post = null;

    #[ORM\ManyToOne(targetEntity: CommunityComment::class)]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?CommunityComment $comment = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('LIKE','DISLIKE') NOT NULL DEFAULT 'LIKE'")]
    private ?string $vote_type = 'LIKE';

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

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

    public function getVoteType(): ?string
    {
        return $this->vote_type;
    }

    public function setVoteType(string $vote_type): static
    {
        $this->vote_type = $vote_type;
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
