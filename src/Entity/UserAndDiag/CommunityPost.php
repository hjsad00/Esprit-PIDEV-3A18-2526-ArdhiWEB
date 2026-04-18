<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\UserAndDiag\User;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\CommunityPostRepository::class)]
#[ORM\Table(name: "community_posts")]
class CommunityPost
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $image_url = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $likes = 0;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $dislikes = 0;

    #[ORM\Column(nullable: true, options: ["default" => false])]
    private ?bool $is_resolved = false;

    #[ORM\ManyToOne(targetEntity: CommunityComment::class)]
    #[ORM\JoinColumn(name: 'solution_comment_id', referencedColumnName: 'id', nullable: true)]
    private ?CommunityComment $solutionComment = null;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $views = 0;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $feedImpressions = 0;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $totalReadTime = 0;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $totalFeedDwellTime = 0;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $completedReads = 0;

    #[ORM\Column(nullable: true, options: ["default" => 0])]
    private ?int $mediaClicks = 0;

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

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getImageUrl(): ?string
    {
        return $this->image_url;
    }

    public function setImageUrl(?string $image_url): static
    {
        $this->image_url = $image_url;
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

    public function isResolved(): ?bool
    {
        return $this->is_resolved;
    }

    public function setIsResolved(?bool $is_resolved): static
    {
        $this->is_resolved = $is_resolved;
        return $this;
    }

    public function getSolutionComment(): ?CommunityComment
    {
        return $this->solutionComment;
    }

    public function setSolutionComment(?CommunityComment $solutionComment): static
    {
        $this->solutionComment = $solutionComment;
        return $this;
    }

    public function getViews(): ?int
    {
        return $this->views;
    }

    public function setViews(?int $views): static
    {
        $this->views = $views;
        return $this;
    }

    public function getFeedImpressions(): ?int
    {
        return $this->feedImpressions;
    }

    public function setFeedImpressions(?int $feedImpressions): static
    {
        $this->feedImpressions = $feedImpressions;
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

    public function getTotalFeedDwellTime(): ?int
    {
        return $this->totalFeedDwellTime;
    }

    public function setTotalFeedDwellTime(?int $totalFeedDwellTime): static
    {
        $this->totalFeedDwellTime = $totalFeedDwellTime;
        return $this;
    }

    public function getCompletedReads(): ?int
    {
        return $this->completedReads;
    }

    public function setCompletedReads(?int $completedReads): static
    {
        $this->completedReads = $completedReads;
        return $this;
    }

    public function getMediaClicks(): ?int
    {
        return $this->mediaClicks;
    }

    public function setMediaClicks(?int $mediaClicks): static
    {
        $this->mediaClicks = $mediaClicks;
        return $this;
    }
}
