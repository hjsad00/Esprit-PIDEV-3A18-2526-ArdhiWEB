<?php

namespace App\Entity\UserAndDiag;

use App\Repository\UserAndDiag\TreatmentTaskRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TreatmentTaskRepository::class)]
#[ORM\Table(name: 'treatment_task')]
class TreatmentTask
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: TreatmentPlan::class)]
    #[ORM\JoinColumn(name: 'treatment_plan_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?TreatmentPlan $treatmentPlan = null;

    #[ORM\Column]
    private int $day_offset;

    #[ORM\Column(length: 255)]
    private string $task_description;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('PENDING','COMPLETED','MISSED') DEFAULT 'PENDING'", nullable: true)]
    private ?string $status = 'PENDING';

    #[ORM\Column(nullable: true)]
    private ?float $tech_x = 0;

    #[ORM\Column(nullable: true)]
    private ?float $tech_y = 0;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTreatmentPlan(): ?TreatmentPlan
    {
        return $this->treatmentPlan;
    }

    public function setTreatmentPlan(?TreatmentPlan $treatmentPlan): static
    {
        $this->treatmentPlan = $treatmentPlan;
        return $this;
    }

    public function getDayOffset(): ?int
    {
        return $this->day_offset ?? null;
    }

    public function setDayOffset(int $day_offset): static
    {
        $this->day_offset = $day_offset;
        return $this;
    }

    public function getTaskDescription(): ?string
    {
        return $this->task_description ?? null;
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

    public function getTechX(): ?float
    {
        return $this->tech_x;
    }

    public function setTechX(?float $tech_x): static
    {
        $this->tech_x = $tech_x;
        return $this;
    }

    public function getTechY(): ?float
    {
        return $this->tech_y;
    }

    public function setTechY(?float $tech_y): static
    {
        $this->tech_y = $tech_y;
        return $this;
    }
}
