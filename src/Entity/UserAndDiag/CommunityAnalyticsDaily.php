<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: "community_analytics_daily")]
class CommunityAnalyticsDaily
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: CommunityPost::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?CommunityPost $post = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(nullable: false, options: ["default" => 0])]
    private ?int $views = 0;

    #[ORM\Column(nullable: false, options: ["default" => 0])]
    private ?int $readTime = 0;

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getViews(): ?int
    {
        return $this->views;
    }

    public function setViews(int $views): static
    {
        $this->views = $views;
        return $this;
    }

    public function getReadTime(): ?int
    {
        return $this->readTime;
    }

    public function setReadTime(int $readTime): static
    {
        $this->readTime = $readTime;
        return $this;
    }
}
