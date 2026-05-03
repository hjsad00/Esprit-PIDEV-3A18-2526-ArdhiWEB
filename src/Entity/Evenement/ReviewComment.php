<?php

namespace App\Entity\Evenement;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\UserAndDiag\User;
use App\Repository\Evenement\ReviewCommentRepository;

#[ORM\Entity(repositoryClass: ReviewCommentRepository::class)]
#[ORM\Table(name: 'review_comments')]
class ReviewComment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Participation::class)]
    #[ORM\JoinColumn(name: 'participation_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Participation $participation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(type: Types::TEXT)]
    private string $content;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $created_at;

    #[ORM\ManyToOne(targetEntity: ReviewComment::class, inversedBy: 'replies')]
    #[ORM\JoinColumn(name: 'parent_comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?ReviewComment $parentComment = null;

    /**
     * @var Collection<int, ReviewComment>
     */
    #[ORM\OneToMany(mappedBy: 'parentComment', targetEntity: ReviewComment::class, cascade: ['remove'])]
    private Collection $replies;

    public function __construct()
    {
        if (array_key_exists('__PHPSTAN_ENTITY_ID_HINT', $_SERVER)) {
            $this->id = 0;
        }
        $this->created_at = new \DateTime();
        $this->replies = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParticipation(): ?Participation
    {
        return $this->participation;
    }

    public function setParticipation(?Participation $participation): static
    {
        $this->participation = $participation;
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

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): static
    {
        $this->content = $content;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeInterface $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getParentComment(): ?ReviewComment
    {
        return $this->parentComment;
    }

    public function setParentComment(?ReviewComment $parentComment): static
    {
        $this->parentComment = $parentComment;
        return $this;
    }

    /**
     * @return Collection<int, ReviewComment>
     */
    public function getReplies(): Collection
    {
        return $this->replies;
    }

    /**
     * @param Collection<int, ReviewComment> $replies
     */
    public function setReplies(Collection $replies): static
    {
        $this->replies = $replies;
        return $this;
    }
}
