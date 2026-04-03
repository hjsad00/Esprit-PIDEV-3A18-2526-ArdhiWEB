<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\FarmHealthReportRepository::class)]
class FarmHealthReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FarmHealthScan::class)]
    #[ORM\JoinColumn(name: 'scan_id', referencedColumnName: 'id', nullable: false)]
    private ?FarmHealthScan $scan = null;

    #[ORM\Column(nullable: true)]
    private ?int $health_score = null;

    #[ORM\Column(nullable: true)]
    private ?int $biodiversity_score = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $llava_analysis = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $generated_at = null;

    public function __construct()
    {
        $this->generated_at = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScan(): ?FarmHealthScan
    {
        return $this->scan;
    }

    public function setScan(?FarmHealthScan $scan): static
    {
        $this->scan = $scan;
        return $this;
    }

    public function getHealthScore(): ?int
    {
        return $this->health_score;
    }

    public function setHealthScore(?int $health_score): static
    {
        $this->health_score = $health_score;
        return $this;
    }

    public function getBiodiversityScore(): ?int
    {
        return $this->biodiversity_score;
    }

    public function setBiodiversityScore(?int $biodiversity_score): static
    {
        $this->biodiversity_score = $biodiversity_score;
        return $this;
    }

    public function getLlavaAnalysis(): ?string
    {
        return $this->llava_analysis;
    }

    public function setLlavaAnalysis(?string $llava_analysis): static
    {
        $this->llava_analysis = $llava_analysis;
        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeInterface
    {
        return $this->generated_at;
    }

    public function setGeneratedAt(?\DateTimeInterface $generated_at): static
    {
        $this->generated_at = $generated_at;
        return $this;
    }
}
