<?php

namespace App\Entity\Parcelles_Cultures;

use App\Repository\Parcelles_Cultures\CultureRepository;
use App\Validator\Parcelles_Cultures\ValidateCultureDates;
use App\Validator\Parcelles_Cultures\ValidateSurfaceConstraint;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CultureRepository::class)]
#[ORM\Table(name: 'culture')]
#[ValidateCultureDates]
#[ValidateSurfaceConstraint]
class Culture
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private string $nom_culture;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\Choice(choices: ['Céréale', 'Légume', 'Fruit', 'Fourrage', 'Oléagineux', 'Protéagineux', 'Autre'])]
    private string $type_culture;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: ['Printemps', 'Été', 'Automne', 'Hiver'])]
    private string $saison;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull]
    private \DateTimeInterface $date_plantation;

    #[ORM\Column(type: 'date')]
    #[Assert\NotNull]
    #[Assert\GreaterThan(propertyPath: 'date_plantation', message: 'La date de récolte doit être après plantation')]
    private \DateTimeInterface $date_recolte_prevue;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: ['Semée', 'Levée', 'Croissance', 'Floraison', 'Grain', 'Prête', 'Récoltée', 'Arrêtée'])]
    private string $etat_culture = 'Semée';

    #[ORM\Column(type: 'float')]
    #[Assert\GreaterThanOrEqual(0, message: 'Superficie utilisée >= 0')]
    #[Assert\LessThanOrEqual(propertyPath: 'parcelle', 
        message: 'Superficie utilisée dépasse la parcelle', 
        groups: ['create', 'edit'])]
    private float $surface_utilisee;

    #[ORM\Column(type: 'float')]
    #[Assert\GreaterThanOrEqual(0, message: 'Rendement estimé >= 0')]
    private float $rendement_estime;

    #[ORM\ManyToOne(targetEntity: Parcelle::class, inversedBy: 'cultures')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Parcelle $parcelle;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updated_at;

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

    public function setNomCulture(string $nom_culture): self
    {
        $this->nom_culture = $nom_culture;
        return $this;
    }

    public function getTypeCulture(): string
    {
        return $this->type_culture;
    }

    public function setTypeCulture(string $type_culture): self
    {
        $this->type_culture = $type_culture;
        return $this;
    }

    public function getSaison(): string
    {
        return $this->saison;
    }

    public function setSaison(string $saison): self
    {
        $this->saison = $saison;
        return $this;
    }

    public function getDatePlantation(): \DateTimeInterface
    {
        return $this->date_plantation;
    }

    public function setDatePlantation(\DateTimeInterface $date_plantation): self
    {
        $this->date_plantation = $date_plantation;
        return $this;
    }

    public function getDateRecolteProvue(): \DateTimeInterface
    {
        return $this->date_recolte_prevue;
    }

    public function setDateRecolteProvue(\DateTimeInterface $date_recolte_prevue): self
    {
        $this->date_recolte_prevue = $date_recolte_prevue;
        return $this;
    }

    public function getEtatCulture(): string
    {
        return $this->etat_culture;
    }

    public function setEtatCulture(string $etat_culture): self
    {
        $this->etat_culture = $etat_culture;
        return $this;
    }

    public function getSurfaceUtilisee(): float
    {
        return $this->surface_utilisee;
    }

    public function setSurfaceUtilisee(float $surface_utilisee): self
    {
        $this->surface_utilisee = $surface_utilisee;
        return $this;
    }

    public function getRendementEstime(): float
    {
        return $this->rendement_estime;
    }

    public function setRendementEstime(float $rendement_estime): self
    {
        $this->rendement_estime = $rendement_estime;
        return $this;
    }

    public function getParcelle(): Parcelle
    {
        return $this->parcelle;
    }

    public function setParcelle(Parcelle $parcelle): self
    {
        $this->parcelle = $parcelle;
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

    /**
     * Production estimée = surface_utilisee × rendement_estime
     */
    public function getProductionEstimee(): float
    {
        return $this->surface_utilisee * $this->rendement_estime;
    }

    /**
     * Nombre de jours entre plantation et récolte prévue
     */
    public function getJoursVegetation(): int
    {
        return $this->date_recolte_prevue->diff($this->date_plantation)->days;
    }
}
