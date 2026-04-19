<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\UserAndDiag\User;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\CommunityCommentRepository::class)]
#[ORM\Table(name: "community_comments")]
class CommunityComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CommunityPost::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?CommunityPost $post = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $content = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $likes = 0;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $dislikes = 0;

    #[ORM\Column(nullable: true, options: ["default" => false])]
    private ?bool $is_solution = false;

    #[ORM\ManyToOne(targetEntity: CommunityComment::class)]
    #[ORM\JoinColumn(name: 'parent_comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?CommunityComment $parentComment = null;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $totalReadTime = 0;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
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

    public function getLikes(): ?int
    {
        return $this->likes;
    }

    public function setLikes(?int $likes): static
    {
        $this->likes = $likes;
        return $this;
    }

    public function getDislikes(): ?int
    {
        return $this->dislikes;
    }

    public function setDislikes(?int $dislikes): static
    {
        $this->dislikes = $dislikes;
        return $this;
    }

    public function isSolution(): ?bool
    {
        return $this->is_solution;
    }

    public function setIsSolution(?bool $is_solution): static
    {
        $this->is_solution = $is_solution;
        return $this;
    }

    public function getParentComment(): ?CommunityComment
    {
        return $this->parentComment;
    }

    public function setParentComment(?CommunityComment $parentComment): static
    {
        $this->parentComment = $parentComment;
        return $this;
    }

    public function getTotalReadTime(): ?int
    {
        return $this->totalReadTime;
    }

    public function setTotalReadTime(?int $totalReadTime): static
    {
        $this->totalReadTime = $totalReadTime;
        return $this;
    }
}
