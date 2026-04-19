<?php

namespace App\Entity\UserAndDiag;

use App\Repository\UserAndDiag\TreatmentPlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

#[ORM\Entity(repositoryClass: TreatmentPlanRepository::class)]
#[ORM\Table(name: 'treatment_plan')]
class TreatmentPlan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Diagnostic::class)]
    #[ORM\JoinColumn(name: 'diagnostic_id', referencedColumnName: 'id', nullable: false)]
    private ?Diagnostic $diagnostic = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $start_date = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('ACTIVE','COMPLETED','ABANDONED') DEFAULT 'ACTIVE'", nullable: true)]
    private ?string $status = 'ACTIVE';

    #[ORM\OneToMany(mappedBy: 'treatmentPlan', targetEntity: TreatmentTask::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $tasks;

    public function __construct()
    {
        $this->start_date = new \DateTime();
        $this->tasks = new ArrayCollection();
    }

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

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->start_date;
    }

    public function setStartDate(?\DateTimeInterface $start_date): static
    {
        $this->start_date = $start_date;
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

    /**
     * @return Collection<int, TreatmentTask>
     */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(TreatmentTask $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setTreatmentPlan($this);
        }

        return $this;
    }

    public function removeTask(TreatmentTask $task): static
    {
        if ($this->tasks->removeElement($task)) {
            // set the owning side to null (unless already changed)
            if ($task->getTreatmentPlan() === $this) {
                $task->setTreatmentPlan(null);
            }
        }

        return $this;
    }
}
