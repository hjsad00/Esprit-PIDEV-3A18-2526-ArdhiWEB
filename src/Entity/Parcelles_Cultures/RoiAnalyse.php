<?php

namespace App\Entity\Parcelles_Cultures;

use App\Repository\Parcelles_Cultures\RoiAnalyseRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RoiAnalyseRepository::class)]
#[ORM\Table(name: 'roi_analyses')]
class RoiAnalyse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(name: 'parcelle_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    private ?Parcelle $parcelle = null;

    #[ORM\Column(length: 100)]
    private string $culture = '';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $roi = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $marge = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $revenu = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $cout_total = 0;

    #[ORM\Column(length: 50)]
    private string $niveau = '';

    #[ORM\Column(length: 50)]
    private string $risque = '';

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $conseils = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $alternative = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $created_at;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $updated_at;

    public function __construct()
    {
        $this->created_at = new \DateTime();
        $this->updated_at = new \DateTime();
    }

    // Getters & Setters
    
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getParcelle(): ?Parcelle
    {
        return $this->parcelle;
    }

    public function setParcelle(?Parcelle $parcelle): self
    {
        $this->parcelle = $parcelle;
        return $this;
    }

    public function getCulture(): string
    {
        return $this->culture;
    }

    public function setCulture(string $culture): self
    {
        $this->culture = $culture;
        return $this;
    }

    public function getRoi(): float
    {
        return $this->roi;
    }

    public function setRoi(float $roi): self
    {
        $this->roi = $roi;
        return $this;
    }

    public function getMarge(): float
    {
        return $this->marge;
    }

    public function setMarge(float $marge): self
    {
        $this->marge = $marge;
        return $this;
    }

    public function getRevenu(): float
    {
        return $this->revenu;
    }

    public function setRevenu(float $revenu): self
    {
        $this->revenu = $revenu;
        return $this;
    }

    public function getCoutTotal(): float
    {
        return $this->cout_total;
    }

    public function setCoutTotal(float $cout_total): self
    {
        $this->cout_total = $cout_total;
        return $this;
    }

    public function getNiveau(): string
    {
        return $this->niveau;
    }

    public function setNiveau(string $niveau): self
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getRisque(): string
    {
        return $this->risque;
    }

    public function setRisque(string $risque): self
    {
        $this->risque = $risque;
        return $this;
    }

    public function getConseils(): ?array
    {
        return $this->conseils;
    }

    public function setConseils(?array $conseils): self
    {
        $this->conseils = $conseils;
        return $this;
    }

    public function getAlternative(): ?string
    {
        return $this->alternative;
    }

    public function setAlternative(?string $alternative): self
    {
        $this->alternative = $alternative;
        return $this;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
    }

    public function getUpdatedAt(): \DateTime
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(\DateTime $updated_at): self
    {
        $this->updated_at = $updated_at;
        return $this;
    }
}
