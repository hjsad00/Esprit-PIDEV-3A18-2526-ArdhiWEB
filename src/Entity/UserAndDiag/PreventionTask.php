<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\PreventionTaskRepository::class)]
class PreventionTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: PreventionPlan::class)]
    #[ORM\JoinColumn(name: 'prevention_plan_id', referencedColumnName: 'id', nullable: false)]
    private ?PreventionPlan $preventionPlan = null;

    #[ORM\Column]
    private ?int $day_offset = null;

    #[ORM\Column(length: 255)]
    private ?string $task_description = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('PENDING','COMPLETED','MISSED') DEFAULT 'PENDING'", nullable: true)]
    private ?string $status = 'PENDING';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $proof_photo_url = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completed_at = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPreventionPlan(): ?PreventionPlan
    {
        return $this->preventionPlan;
    }

    public function setPreventionPlan(?PreventionPlan $preventionPlan): static
    {
        $this->preventionPlan = $preventionPlan;
        return $this;
    }

    public function getDayOffset(): ?int
    {
        return $this->day_offset;
    }

    public function setDayOffset(int $day_offset): static
    {
        $this->day_offset = $day_offset;
        return $this;
    }

    public function getTaskDescription(): ?string
    {
        return $this->task_description;
    }

    public function setTaskDescription(string $task_description): static
    {
        $this->task_description = $task_description;
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

    public function getProofPhotoUrl(): ?string
    {
        return $this->proof_photo_url;
    }

    public function setProofPhotoUrl(?string $proof_photo_url): static
    {
        $this->proof_photo_url = $proof_photo_url;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completed_at;
    }

    public function setCompletedAt(?\DateTimeInterface $completed_at): static
    {
        $this->completed_at = $completed_at;
        return $this;
    }
}
