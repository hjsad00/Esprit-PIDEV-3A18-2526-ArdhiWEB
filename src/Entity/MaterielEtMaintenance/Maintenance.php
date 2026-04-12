<?php

namespace App\Entity\MaterielEtMaintenance;

use App\Entity\MaterielEtMaintenance\Materiel;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: 'App\Repository\MaterielEtMaintenance\MaintenanceRepository')]
#[ORM\Table(name: 'maintenance')]
class Maintenance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_maintenance', type: 'integer')]
    private ?int $id_maintenance = null;

    #[ORM\ManyToOne(targetEntity: Materiel::class, inversedBy: 'maintenances')]
    #[ORM\JoinColumn(name: 'materiel_id', referencedColumnName: 'id_materiel', onDelete: 'CASCADE', nullable: false)]
    #[Assert\NotNull(message: 'Le matériel associé est obligatoire.')]
    private ?Materiel $materiel = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $description = null;

    #[ORM\Column(name: 'date_maintenance', type: 'datetime')]
    #[Assert\NotBlank(message: 'La date et l\'heure de maintenance sont obligatoires.')]
    #[Assert\GreaterThanOrEqual('today', message: "Tu ne peux pas mettre une date de maintenance au passé.")]
    #[Assert\Expression(
        "value == null or value.format('w') != 0",
        message: "La date de maintenance ne peut pas être prévue un dimanche."
    )]
    #[Assert\Expression(
        "value == null or (value.format('G') < 16) or (value.format('G') == 16 and value.format('i') == 0)",
        message: "L'heure de planification ne peut pas dépasser 16:00."
    )]
    private ?\DateTimeInterface $date_maintenance = null;

    #[ORM\Column(name: 'google_calendar_event_id', type: 'string', length: 255, nullable: true)]
    private ?string $google_calendar_event_id = null;

    #[ORM\Column(name: 'statut_maintenance', type: 'string', length: 50, nullable: true, options: ['default' => 'planifiee'])]
    #[Assert\Choice(
        choices: ['planifiee', 'en_attente', 'en_cours', 'verifie', 'terminee', 'annulee'],
        message: 'Statut invalide.'
    )]
    private ?string $statut_maintenance = 'planifiee';

    #[ORM\Column(name: 'date_planifiee', type: 'date', nullable: true)]
    private ?\DateTimeInterface $date_planifiee = null;

    #[ORM\Column(name: 'date_realisee', type: 'date', nullable: true)]
    private ?\DateTimeInterface $date_realisee = null;

    #[ORM\Column(name: 'type_maintenance', type: 'string', length: 50, nullable: true, options: ['default' => 'preventive'])]
    #[Assert\Choice(
        choices: ['preventive', 'corrective', 'urgente'],
        message: 'Type de maintenance invalide.'
    )]
    private ?string $type_maintenance = 'preventive';

    public function getIdMaintenance(): ?int { return $this->id_maintenance; }

    public function getMateriel(): ?Materiel { return $this->materiel; }
    public function setMateriel(?Materiel $materiel): self { $this->materiel = $materiel; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): self { $this->description = $description; return $this; }

    public function getDateMaintenance(): ?\DateTimeInterface { return $this->date_maintenance; }
    public function setDateMaintenance(?\DateTimeInterface $date): self { $this->date_maintenance = $date; return $this; }

    public function getGoogleCalendarEventId(): ?string { return $this->google_calendar_event_id; }
    public function setGoogleCalendarEventId(?string $id): self { $this->google_calendar_event_id = $id; return $this; }

    public function getStatutMaintenance(): ?string { return $this->statut_maintenance; }
    public function setStatutMaintenance(?string $statut): self { $this->statut_maintenance = $statut; return $this; }

    public function getDatePlanifiee(): ?\DateTimeInterface { return $this->date_planifiee; }
    public function setDatePlanifiee(?\DateTimeInterface $date): self { $this->date_planifiee = $date; return $this; }

    public function getDateRealisee(): ?\DateTimeInterface { return $this->date_realisee; }
    public function setDateRealisee(?\DateTimeInterface $date): self { $this->date_realisee = $date; return $this; }

    public function getTypeMaintenance(): ?string { return $this->type_maintenance; }
    public function setTypeMaintenance(?string $type): self { $this->type_maintenance = $type; return $this; }
}
