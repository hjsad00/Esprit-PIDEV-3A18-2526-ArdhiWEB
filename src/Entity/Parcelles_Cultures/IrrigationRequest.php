<?php

namespace App\Entity\Parcelles_Cultures;

use App\Repository\Parcelles_Cultures\IrrigationRequestRepository;
use App\Validator\Parcelles_Cultures\ValidIrrigationTemperatures;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: IrrigationRequestRepository::class)]
#[ORM\Table(name: 'irrigation_request')]
#[ValidIrrigationTemperatures]
class IrrigationRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(message: 'La date est obligatoire')]
    private \DateTimeInterface $date;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'La température moyenne est obligatoire')]
    private string $temperature_moyenne;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'La température max est obligatoire')]
    private string $temperature_max;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'La température min est obligatoire')]
    private string $temperature_min;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Les précipitations sont obligatoires')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'Les précipitations doivent être >= 0')]
    private string $precipitations;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\NotBlank(message: 'L\'humidité est obligatoire')]
    #[Assert\Range(min: 0, max: 100, notInRangeMessage: 'L\'humidité doit être entre 0 et 100')]
    private string $humidite;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\NotBlank(message: 'Le coefficient Kc est obligatoire')]
    #[Assert\GreaterThan(value: 0, message: 'Kc doit être > 0')]
    private string $kc;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $volume_litres = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\ManyToOne(targetEntity: Parcelle::class, inversedBy: 'irrigationRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Parcelle $parcelle = null;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getTemperatureMoyenne(): string
    {
        return $this->temperature_moyenne;
    }

    public function setTemperatureMoyenne(string $temperature_moyenne): static
    {
        $this->temperature_moyenne = $temperature_moyenne;
        return $this;
    }

    public function getTemperatureMax(): string
    {
        return $this->temperature_max;
    }

    public function setTemperatureMax(string $temperature_max): static
    {
        $this->temperature_max = $temperature_max;
        return $this;
    }

    public function getTemperatureMin(): string
    {
        return $this->temperature_min;
    }

    public function setTemperatureMin(string $temperature_min): static
    {
        $this->temperature_min = $temperature_min;
        return $this;
    }

    public function getPrecipitations(): string
    {
        return $this->precipitations;
    }

    public function setPrecipitations(string $precipitations): static
    {
        $this->precipitations = $precipitations;
        return $this;
    }

    public function getHumidite(): string
    {
        return $this->humidite;
    }

    public function setHumidite(string $humidite): static
    {
        $this->humidite = $humidite;
        return $this;
    }

    public function getKc(): string
    {
        return $this->kc;
    }

    public function setKc(string $kc): static
    {
        $this->kc = $kc;
        return $this;
    }

    public function getVolumeLitres(): ?string
    {
        return $this->volume_litres;
    }

    public function setVolumeLitres(?string $volume_litres): static
    {
        $this->volume_litres = $volume_litres;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getParcelle(): ?Parcelle
    {
        return $this->parcelle;
    }

    public function setParcelle(?Parcelle $parcelle): static
    {
        $this->parcelle = $parcelle;
        return $this;
    }
}
