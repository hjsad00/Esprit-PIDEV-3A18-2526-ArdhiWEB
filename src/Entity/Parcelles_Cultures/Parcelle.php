<?php

namespace App\Entity\Parcelles_Cultures;

use App\Repository\Parcelles_Cultures\ParcelleRepository;
use App\Entity\UserAndDiag\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParcelleRepository::class)]
#[ORM\Table(name: 'parcelle')]
class Parcelle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'La surface est obligatoire')]
    #[Assert\GreaterThan(value: 0, message: 'La surface doit être supérieure à 0')]
    private ?string $surface = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'La localisation est obligatoire')]
    private ?string $localisation = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le type de sol est obligatoire')]
    private ?string $type_sol = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le système d\'irrigation est obligatoire')]
    private ?string $systeme_irrigation = null;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'active'])]
    private string $statut = 'active';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 6, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 6, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $polygon_geojson = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'parcelles')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $agriculteur = null;

    /**
     * @var Collection<int, Culture>
     */
    #[ORM\OneToMany(targetEntity: Culture::class, mappedBy: 'parcelle', cascade: ['remove'])]
    private Collection $cultures;

    /**
     * @var Collection<int, IrrigationRequest>
     */
    #[ORM\OneToMany(targetEntity: IrrigationRequest::class, mappedBy: 'parcelle', cascade: ['remove'])]
    private Collection $irrigationRequests;

    /**
     * @var Collection<int, CreditDossier>
     */
    #[ORM\OneToMany(targetEntity: CreditDossier::class, mappedBy: 'parcelle', cascade: ['remove'])]
    private Collection $creditDossiers;

    public function __construct()
    {
        $this->cultures = new ArrayCollection();
        $this->irrigationRequests = new ArrayCollection();
        $this->creditDossiers = new ArrayCollection();
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSurface(): ?string
    {
        return $this->surface;
    }

    public function setSurface(string $surface): static
    {
        $this->surface = $surface;
        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): static
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getTypeSol(): ?string
    {
        return $this->type_sol;
    }

    public function setTypeSol(string $type_sol): static
    {
        $this->type_sol = $type_sol;
        return $this;
    }

    public function getSystemeIrrigation(): ?string
    {
        return $this->systeme_irrigation;
    }

    public function setSystemeIrrigation(string $systeme_irrigation): static
    {
        $this->systeme_irrigation = $systeme_irrigation;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;
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

    /**
     * @return Collection<int, Culture>
     */
    public function getCultures(): Collection
    {
        return $this->cultures;
    }

    public function addCulture(Culture $culture): static
    {
        if (!$this->cultures->contains($culture)) {
            $this->cultures->add($culture);
            $culture->setParcelle($this);
        }
        return $this;
    }

    public function removeCulture(Culture $culture): static
    {
        if ($this->cultures->removeElement($culture)) {
            if ($culture->getParcelle() === $this) {
                $culture->setParcelle(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, IrrigationRequest>
     */
    public function getIrrigationRequests(): Collection
    {
        return $this->irrigationRequests;
    }

    public function addIrrigationRequest(IrrigationRequest $irrigationRequest): static
    {
        if (!$this->irrigationRequests->contains($irrigationRequest)) {
            $this->irrigationRequests->add($irrigationRequest);
            $irrigationRequest->setParcelle($this);
        }
        return $this;
    }

    public function removeIrrigationRequest(IrrigationRequest $irrigationRequest): static
    {
        if ($this->irrigationRequests->removeElement($irrigationRequest)) {
            if ($irrigationRequest->getParcelle() === $this) {
                $irrigationRequest->setParcelle(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, CreditDossier>
     */
    public function getCreditDossiers(): Collection
    {
        return $this->creditDossiers;
    }

    public function addCreditDossier(CreditDossier $creditDossier): static
    {
        if (!$this->creditDossiers->contains($creditDossier)) {
            $this->creditDossiers->add($creditDossier);
            $creditDossier->setParcelle($this);
        }
        return $this;
    }

    public function removeCreditDossier(CreditDossier $creditDossier): static
    {
        if ($this->creditDossiers->removeElement($creditDossier)) {
            if ($creditDossier->getParcelle() === $this) {
                $creditDossier->setParcelle(null);
            }
        }
        return $this;
    }

    public function getPolygonGeojson(): ?array
    {
        return $this->polygon_geojson;
    }

    public function setPolygonGeojson(?array $polygon_geojson): static
    {
        $this->polygon_geojson = $polygon_geojson;
        return $this;
    }
}
