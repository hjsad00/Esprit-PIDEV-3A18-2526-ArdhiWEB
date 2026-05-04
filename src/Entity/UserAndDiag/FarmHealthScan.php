<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\UserAndDiag\User;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\FarmHealthScanRepository::class)]
class FarmHealthScan
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\Column(length: 100)]
    private string $crop_type;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $planting_date;

    #[ORM\Column(length: 50)]
    private string $growth_stage;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $concerns = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photo_crops = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photo_soil = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photo_edges = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photo_insects = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photo_spacing = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $photo_overview = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $scan_date = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('PENDING','PROCESSING','COMPLETED','FAILED') DEFAULT 'PENDING'", nullable: true)]
    private ?string $status = 'PENDING';

    // Removed fields not in DB

    public function __construct()
    {
        $this->scan_date = new \DateTime();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCropType(): ?string
    {
        return $this->crop_type ?? null;
    }

    public function setCropType(string $crop_type): static
    {
        $this->crop_type = $crop_type;
        return $this;
    }

    public function getPlantingDate(): ?\DateTimeInterface
    {
        return $this->planting_date ?? null;
    }

    public function setPlantingDate(\DateTimeInterface $planting_date): static
    {
        $this->planting_date = $planting_date;
        return $this;
    }

    public function getGrowthStage(): ?string
    {
        return $this->growth_stage ?? null;
    }

    public function setGrowthStage(string $growth_stage): static
    {
        $this->growth_stage = $growth_stage;
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

    public function getConcerns(): ?string
    {
        return $this->concerns;
    }

    public function setConcerns(?string $concerns): static
    {
        $this->concerns = $concerns;
        return $this;
    }

    public function getPhotoCrops(): ?string
    {
        return $this->photo_crops;
    }

    public function setPhotoCrops(?string $photo_crops): static
    {
        $this->photo_crops = $photo_crops;
        return $this;
    }

    public function getPhotoSoil(): ?string
    {
        return $this->photo_soil;
    }

    public function setPhotoSoil(?string $photo_soil): static
    {
        $this->photo_soil = $photo_soil;
        return $this;
    }

    public function getPhotoEdges(): ?string
    {
        return $this->photo_edges;
    }

    public function setPhotoEdges(?string $photo_edges): static
    {
        $this->photo_edges = $photo_edges;
        return $this;
    }

    public function getPhotoInsects(): ?string
    {
        return $this->photo_insects;
    }

    public function setPhotoInsects(?string $photo_insects): static
    {
        $this->photo_insects = $photo_insects;
        return $this;
    }

    public function getPhotoSpacing(): ?string
    {
        return $this->photo_spacing;
    }

    public function setPhotoSpacing(?string $photo_spacing): static
    {
        $this->photo_spacing = $photo_spacing;
        return $this;
    }

    public function getPhotoOverview(): ?string
    {
        return $this->photo_overview;
    }

    public function setPhotoOverview(?string $photo_overview): static
    {
        $this->photo_overview = $photo_overview;
        return $this;
    }

    public function getScanDate(): ?\DateTimeInterface
    {
        return $this->scan_date;
    }

    public function setScanDate(?\DateTimeInterface $scan_date): static
    {
        $this->scan_date = $scan_date;
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

    // Removed Getters/Setters not in DB
}
