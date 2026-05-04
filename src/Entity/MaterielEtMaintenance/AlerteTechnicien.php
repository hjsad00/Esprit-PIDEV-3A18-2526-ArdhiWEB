<?php

namespace App\Entity\MaterielEtMaintenance;

use App\Entity\UserAndDiag\User;
use App\Repository\MaterielEtMaintenance\AlerteTechnicienRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AlerteTechnicienRepository::class)]
class AlerteTechnicien
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateSignalement = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $statut = 'non_lu'; // non_lu, lu

    #[ORM\ManyToOne(inversedBy: 'alerteTechniciens')]
    #[ORM\JoinColumn(name: "id_materiel", referencedColumnName: "id_materiel", nullable: false, onDelete: 'CASCADE')]
    private ?Materiel $materiel = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: "agriculteur_id", referencedColumnName: "id", nullable: false)]
    private ?User $agriculteur = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDateSignalement(): ?\DateTimeInterface
    {
        return $this->dateSignalement;
    }

    public function setDateSignalement(\DateTimeInterface $dateSignalement): static
    {
        $this->dateSignalement = $dateSignalement;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getMateriel(): ?Materiel
    {
        return $this->materiel;
    }

    public function setMateriel(?Materiel $materiel): static
    {
        $this->materiel = $materiel;
        return $this;
    }

    public function getAgriculteur(): ?User
    {
        return $this->agriculteur;
    }

    public function setAgriculteur(?User $agriculteur): static
    {
        $this->agriculteur = $agriculteur;
        return $this;
    }
}
