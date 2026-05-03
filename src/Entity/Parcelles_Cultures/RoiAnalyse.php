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
    private string $roi = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $marge = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $revenu = '0';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $cout_total = '0';

    #[ORM\Column(length: 50)]
    private string $niveau = '';

    #[ORM\Column(length: 50)]
    private string $risque = '';

    /**
     * @var array<int,string>|null
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $conseils = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $alternative = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $created_at;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updated_at;

    public function __construct()
    {
        $this->created_at = new \DateTimeImmutable();
        $this->updated_at = new \DateTimeImmutable();
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

    public function getRoi(): string
    {
        return $this->roi;
    }

    public function setRoi(string $roi): self
    {
        $this->roi = $roi;
        return $this;
    }

    public function getMarge(): string
    {
        return $this->marge;
    }

    public function setMarge(string $marge): self
    {
        $this->marge = $marge;
        return $this;
    }

    public function getRevenu(): string
    {
        return $this->revenu;
    }

    public function setRevenu(string $revenu): self
    {
        $this->revenu = $revenu;
        return $this;
    }

    public function getCoutTotal(): string
    {
        return $this->cout_total;
    }

    public function setCoutTotal(string $cout_total): self
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

    /**
     * @return string[]|null
     */
    public function getConseils(): ?array
    {
        return $this->conseils;
    }

    /**
     * @param string[]|null $conseils
     */
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): self
    {
        $this->created_at = $created_at;
        return $this;
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
