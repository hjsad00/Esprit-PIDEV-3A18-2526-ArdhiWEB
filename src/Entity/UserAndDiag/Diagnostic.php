<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\DiagnosticRepository::class)]
class Diagnostic
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_scan = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image_scannee = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $resultat_ia = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true, options: ["default" => 0])]
    private ?float $confiance = 0;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location_label = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $severity = null;

    public function __construct()
    {
        $this->date_scan = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDateScan(): ?\DateTimeInterface
    {
        return $this->date_scan;
    }

    public function setDateScan(?\DateTimeInterface $date_scan): static
    {
        $this->date_scan = $date_scan;
        return $this;
    }

    public function getImageScannee(): ?string
    {
        return $this->image_scannee;
    }

    public function setImageScannee(?string $image_scannee): static
    {
        $this->image_scannee = $image_scannee;
        return $this;
    }

    public function getResultatIa(): ?string
    {
        return $this->resultat_ia;
    }

    public function setResultatIa(?string $resultat_ia): static
    {
        $this->resultat_ia = $resultat_ia;
        return $this;
    }

    public function getConfiance(): ?float
    {
        return $this->confiance;
    }

    public function setConfiance(?float $confiance): static
    {
        $this->confiance = $confiance;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getLocationLabel(): ?string
    {
        return $this->location_label;
    }

    public function setLocationLabel(?string $location_label): static
    {
        $this->location_label = $location_label;
        return $this;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(?string $severity): static
    {
        $this->severity = $severity;
        return $this;
    }
}
