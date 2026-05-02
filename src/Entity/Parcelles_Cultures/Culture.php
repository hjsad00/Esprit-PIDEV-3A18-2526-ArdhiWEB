<?php

namespace App\Entity\Parcelles_Cultures;

use App\Repository\Parcelles_Cultures\CultureRepository;
use App\Validator\Parcelles_Cultures\ValidCultureDates;
use App\Validator\Parcelles_Cultures\ValidSurfaceConstraint;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CultureRepository::class)]
#[ORM\Table(name: 'culture')]
#[ValidCultureDates]
#[ValidSurfaceConstraint]
class Culture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private int $id;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Le nom de la culture est obligatoire')]
    private string $nom_culture;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le type de culture est obligatoire')]
    private string $type_culture;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'La saison est obligatoire')]
    private string $saison;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(message: 'La date de plantation est obligatoire')]
    private \DateTimeInterface $date_plantation;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(message: 'La date de récolte prévue est obligatoire')]
    private \DateTimeInterface $date_recolte_prevue;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'active'])]
    private string $etat_culture = 'active';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'La surface utilisée est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0, message: 'La surface utilisée doit être >= 0')]
    private string $surface_utilisee;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le rendement estimé est obligatoire')]
    #[Assert\GreaterThan(value: 0, message: 'Le rendement estimé doit être > 0')]
    private string $rendement_estime;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $production_estimee = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updated_at;

    #[ORM\ManyToOne(targetEntity: Parcelle::class, inversedBy: 'cultures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Parcelle $parcelle = null;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomCulture(): string
    {
        return $this->nom_culture;
    }

    public function setNomCulture(string $nom_culture): static
    {
        $this->nom_culture = $nom_culture;
        return $this;
    }

    public function getTypeCulture(): string
    {
        return $this->type_culture;
    }

    public function setTypeCulture(string $type_culture): static
    {
        $this->type_culture = $type_culture;
        return $this;
    }

    public function getSaison(): string
    {
        return $this->saison;
    }

    public function setSaison(string $saison): static
    {
        $this->saison = $saison;
        return $this;
    }

    public function getDatePlantation(): \DateTimeInterface
    {
        return $this->date_plantation;
    }

    public function setDatePlantation(\DateTimeInterface $date_plantation): static
    {
        $this->date_plantation = $date_plantation;
        return $this;
    }

    public function getDateRecoltePrevue(): \DateTimeInterface
    {
        return $this->date_recolte_prevue;
    }

    public function setDateRecoltePrevue(\DateTimeInterface $date_recolte_prevue): static
    {
        $this->date_recolte_prevue = $date_recolte_prevue;
        return $this;
    }

    public function getEtatCulture(): string
    {
        return $this->etat_culture;
    }

    public function setEtatCulture(string $etat_culture): static
    {
        $this->etat_culture = $etat_culture;
        return $this;
    }

    public function getSurfaceUtilisee(): string
    {
        return $this->surface_utilisee;
    }

    public function setSurfaceUtilisee(float|string|int $surface_utilisee): static
    {
        $this->surface_utilisee = (string) $surface_utilisee;
        return $this;
    }

    public function getRendementEstime(): string
    {
        return $this->rendement_estime;
    }

    public function setRendementEstime(float|string|int $rendement_estime): static
    {
        $this->rendement_estime = (string) $rendement_estime;
        return $this;
    }

    public function getProductionEstimee(): ?string
    {
        return $this->production_estimee;
    }

    public function setProductionEstimee(?string $production_estimee): static
    {
        $this->production_estimee = $production_estimee;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;
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
