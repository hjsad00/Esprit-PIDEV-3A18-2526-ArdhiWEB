<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\UserAndDiag\User;
use App\Entity\UserAndDiag\Diagnostic;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\ReviewRepository::class)]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Diagnostic::class)]
    #[ORM\JoinColumn(name: 'diagnostic_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Diagnostic $diagnostic = null;

    #[ORM\ManyToOne(targetEntity: TreatmentPlan::class)]
    #[ORM\JoinColumn(name: 'treatment_plan_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?TreatmentPlan $treatment_plan = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'expert_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $expert = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('DIAGNOSIS','PROGRESS','PREVENTION') NOT NULL")]
    private ?string $review_type = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('PENDING','IN_PROGRESS','COMPLETED') DEFAULT 'PENDING'", nullable: true)]
    private ?string $status = 'PENDING';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photo_url = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ai_analysis = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $expert_notes = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('CONTINUE','HEALED','WORSENED')", nullable: true)]
    private ?string $expert_verdict = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $expert_disease_name = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $created_at = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updated_at = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('ACCEPTED','REJECTED','ACKNOWLEDGED')", nullable: true)]
    private ?string $farmer_response = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $ai_proposed_plan = null;

    #[ORM\ManyToOne(targetEntity: PreventionPlan::class)]
    #[ORM\JoinColumn(name: 'prevention_plan_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?PreventionPlan $prevention_plan = null;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    // Getters and Setter
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDiagnostic(): ?Diagnostic
    {
        return $this->diagnostic;
    }

    public function setDiagnostic(?Diagnostic $diagnostic): static
    {
        $this->diagnostic = $diagnostic;
        return $this;
    }

    public function getTreatmentPlan(): ?TreatmentPlan
    {
        return $this->treatment_plan;
    }

    public function setTreatmentPlan(?TreatmentPlan $treatment_plan): static
    {
        $this->treatment_plan = $treatment_plan;
        return $this;
    }

    public function getExpert(): ?User
    {
        return $this->expert;
    }

    public function setExpert(?User $expert): static
    {
        $this->expert = $expert;
        return $this;
    }

    public function getReviewType(): ?string
    {
        return $this->review_type;
    }

    public function setReviewType(string $review_type): static
    {
        $this->review_type = $review_type;
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

    public function getPhotoUrl(): ?string
    {
        return $this->photo_url;
    }

    public function setPhotoUrl(?string $photo_url): static
    {
        $this->photo_url = $photo_url;
        return $this;
    }

    public function getAiAnalysis(): ?string
    {
        return $this->ai_analysis;
    }

    public function setAiAnalysis(?string $ai_analysis): static
    {
        $this->ai_analysis = $ai_analysis;
        return $this;
    }

    public function getExpertNotes(): ?string
    {
        return $this->expert_notes;
    }

    public function setExpertNotes(?string $expert_notes): static
    {
        $this->expert_notes = $expert_notes;
        return $this;
    }

    public function getExpertVerdict(): ?string
    {
        return $this->expert_verdict;
    }

    public function setExpertVerdict(?string $expert_verdict): static
    {
        $this->expert_verdict = $expert_verdict;
        return $this;
    }

    public function getExpertDiseaseName(): ?string
    {
        return $this->expert_disease_name;
    }

    public function setExpertDiseaseName(?string $expert_disease_name): static
    {
        $this->expert_disease_name = $expert_disease_name;
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

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeInterface $updated_at): static
    {
        $this->updated_at = $updated_at;
        return $this;
    }

    public function getFarmerResponse(): ?string
    {
        return $this->farmer_response;
    }

    public function setFarmerResponse(?string $farmer_response): static
    {
        $this->farmer_response = $farmer_response;
        return $this;
    }

    public function getAiProposedPlan(): ?string
    {
        return $this->ai_proposed_plan;
    }

    public function setAiProposedPlan(?string $ai_proposed_plan): static
    {
        $this->ai_proposed_plan = $ai_proposed_plan;
        return $this;
    }

    public function getPreventionPlan(): ?PreventionPlan
    {
        return $this->prevention_plan;
    }

    public function setPreventionPlan(?PreventionPlan $prevention_plan): static
    {
        $this->prevention_plan = $prevention_plan;
        return $this;
    }
}
