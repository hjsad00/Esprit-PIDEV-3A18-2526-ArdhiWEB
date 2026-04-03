<?php

namespace App\Entity\UserAndDiag;

use App\Repository\UserAndDiag\TreatmentPlanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

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

    public function __construct()
    {
        $this->start_date = new \DateTime();
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
}
