<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\PreventionPlanRepository::class)]
class PreventionPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: FarmHealthReport::class)]
    #[ORM\JoinColumn(name: 'report_id', referencedColumnName: 'id', nullable: false)]
    private ?FarmHealthReport $report = null;

    #[ORM\ManyToOne(targetEntity: Vulnerability::class)]
    #[ORM\JoinColumn(name: 'vulnerability_id', referencedColumnName: 'id', nullable: true)]
    private ?Vulnerability $vulnerability = null;

    #[ORM\Column(length: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $problem_summary = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $steps = null;

    #[ORM\Column(nullable: true)]
    private ?int $timeline_days = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $estimated_cost = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $expected_outcome = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('HIGH','MEDIUM','LOW')", nullable: true)]
    private ?string $impact_level = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('ACTIVE','COMPLETED','ABANDONED') DEFAULT 'ACTIVE'", nullable: true)]
    private ?string $status = 'ACTIVE';

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $start_date = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReport(): ?FarmHealthReport
    {
        return $this->report;
    }

    public function setReport(?FarmHealthReport $report): static
    {
        $this->report = $report;
        return $this;
    }

    public function getVulnerability(): ?Vulnerability
    {
        return $this->vulnerability;
    }

    public function setVulnerability(?Vulnerability $vulnerability): static
    {
        $this->vulnerability = $vulnerability;
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

    public function getProblemSummary(): ?string
    {
        return $this->problem_summary;
    }

    public function setProblemSummary(?string $problem_summary): static
    {
        $this->problem_summary = $problem_summary;
        return $this;
    }

    public function getSteps(): ?string
    {
        return $this->steps;
    }

    public function setSteps(string $steps): static
    {
        $this->steps = $steps;
        return $this;
    }

    public function getTimelineDays(): ?int
    {
        return $this->timeline_days;
    }

    public function setTimelineDays(?int $timeline_days): static
    {
        $this->timeline_days = $timeline_days;
        return $this;
    }

    public function getEstimatedCost(): ?float
    {
        return $this->estimated_cost;
    }

    public function setEstimatedCost(?float $estimated_cost): static
    {
        $this->estimated_cost = $estimated_cost;
        return $this;
    }

    public function getExpectedOutcome(): ?string
    {
        return $this->expected_outcome;
    }

    public function setExpectedOutcome(?string $expected_outcome): static
    {
        $this->expected_outcome = $expected_outcome;
        return $this;
    }

    public function getImpactLevel(): ?string
    {
        return $this->impact_level;
    }

    public function setImpactLevel(?string $impact_level): static
    {
        $this->impact_level = $impact_level;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTimeInterface $start_date): static
    {
        $this->start_date = $start_date;
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
