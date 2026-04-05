<?php

namespace App\Entity\Parcelles_Cultures;

use App\Entity\UserAndDiag\User;
use App\Repository\Parcelles_Cultures\ParceleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ParceleRepository::class)]
#[ORM\Table(name: 'parcelle')]
class Parcelle
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'float')]
    #[Assert\GreaterThan(0, message: 'La surface doit être > 0')]
    private float $surface;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private string $localisation;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\Choice(choices: ['Sableux', 'Argileux', 'Limoneux', 'Tourbeux', 'Crayeux'])]
    private string $type_sol;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\Choice(choices: ['Goutte-à-goutte', 'Aspersion', 'Gravitaire', 'Aucune'])]
    private string $systeme_irrigation;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: ['Active', 'Inactive', 'Repos', 'Dégradée'])]
    private string $statut = 'Active';

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    #[Assert\GreaterThanOrEqual(0)]
    private ?float $longitude = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $agriculteur;

    #[ORM\OneToMany(targetEntity: Culture::class, mappedBy: 'parcelle', cascade: ['remove'])]
    private Collection $cultures;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updated_at;

    public function __construct()
    {
        $this->cultures = new ArrayCollection();
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSurface(): float
    {
        return $this->surface;
    }

    public function setSurface(float $surface): self
    {
        $this->surface = $surface;
        return $this;
    }

    public function getLocalisation(): string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): self
    {
        $this->localisation = $localisation;
        return $this;
    }

    public function getTypeSol(): string
    {
        return $this->type_sol;
    }

    public function setTypeSol(string $type_sol): self
    {
        $this->type_sol = $type_sol;
        return $this;
    }

    public function getSystemeIrrigation(): string
    {
        return $this->systeme_irrigation;
    }

    public function setSystemeIrrigation(string $systeme_irrigation): self
    {
        $this->systeme_irrigation = $systeme_irrigation;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): self
    {
        $this->statut = $statut;
        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getAgriculteur(): User
    {
        return $this->agriculteur;
    }

    public function setAgriculteur(User $agriculteur): self
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

    public function addCulture(Culture $culture): self
    {
        if (!$this->cultures->contains($culture)) {
            $this->cultures->add($culture);
            $culture->setParcelle($this);
        }
        return $this;
    }

    public function removeCulture(Culture $culture): self
    {
        if ($this->cultures->removeElement($culture)) {
            if ($culture->getParcelle() === $this) {
                $culture->setParcelle(null);
            }
        }
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }
}
