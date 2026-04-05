<?php

namespace App\Entity\Parcelles_Cultures;

use App\Entity\UserAndDiag\User;
use App\Repository\Parcelles_Cultures\CreditDossierRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CreditDossierRepository::class)]
#[ORM\Table(name: 'credit_dossier')]
class CreditDossier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Parcelle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Parcelle $parcelle;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'integer')]
    #[Assert\GreaterThan(0)]
    private int $duree_annees;

    #[ORM\Column(type: 'string', length: 10)]
    #[Assert\Choice(choices: ['fr', 'en', 'ar'])]
    private string $langue = 'fr';

    #[ORM\Column(type: 'float')]
    #[Assert\GreaterThanOrEqual(0)]
    #[Assert\LessThanOrEqual(10)]
    private float $score_risque;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: ['Faible', 'Modéré', 'Élevé'])]
    private string $niveau_risque;

    #[ORM\Column(type: 'float')]
    #[Assert\GreaterThanOrEqual(0)]
    private float $montant_pret_max;

    #[ORM\Column(type: 'float')]
    #[Assert\GreaterThanOrEqual(0)]
    private float $capacite_remboursement;

    // Scores composantes
    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $score_rentabilite = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $score_stabilite_climat = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $score_diversification = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $score_historique = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $date_creation;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $date_export = null;

    public function __construct()
    {
        $this->date_creation = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getDureeAnnees(): int
    {
        return $this->duree_annees;
    }

    public function setDureeAnnees(int $duree_annees): self
    {
        $this->duree_annees = $duree_annees;
        return $this;
    }

    public function getLangue(): string
    {
        return $this->langue;
    }

    public function setLangue(string $langue): self
    {
        $this->langue = $langue;
        return $this;
    }

    public function getScoreRisque(): float
    {
        return $this->score_risque;
    }

    public function setScoreRisque(float $score_risque): self
    {
        $this->score_risque = $score_risque;
        return $this;
    }

    public function getNiveauRisque(): string
    {
        return $this->niveau_risque;
    }

    public function setNiveauRisque(string $niveau_risque): self
    {
        $this->niveau_risque = $niveau_risque;
        return $this;
    }

    public function getMontantPretMax(): float
    {
        return $this->montant_pret_max;
    }

    public function setMontantPretMax(float $montant_pret_max): self
    {
        $this->montant_pret_max = $montant_pret_max;
        return $this;
    }

    public function getCapaciteRemboursement(): float
    {
        return $this->capacite_remboursement;
    }

    public function setCapaciteRemboursement(float $capacite_remboursement): self
    {
        $this->capacite_remboursement = $capacite_remboursement;
        return $this;
    }

    public function getScoreRentabilite(): ?float
    {
        return $this->score_rentabilite;
    }

    public function setScoreRentabilite(?float $score_rentabilite): self
    {
        $this->score_rentabilite = $score_rentabilite;
        return $this;
    }

    public function getScoreStabiliteClimat(): ?float
    {
        return $this->score_stabilite_climat;
    }

    public function setScoreStabiliteClimat(?float $score_stabilite_climat): self
    {
        $this->score_stabilite_climat = $score_stabilite_climat;
        return $this;
    }

    public function getScoreDiversification(): ?float
    {
        return $this->score_diversification;
    }

    public function setScoreDiversification(?float $score_diversification): self
    {
        $this->score_diversification = $score_diversification;
        return $this;
    }

    public function getScoreHistorique(): ?float
    {
        return $this->score_historique;
    }

    public function setScoreHistorique(?float $score_historique): self
    {
        $this->score_historique = $score_historique;
        return $this;
    }

    public function getDateCreation(): \DateTimeImmutable
    {
        return $this->date_creation;
    }

    public function getDateExport(): ?\DateTimeImmutable
    {
        return $this->date_export;
    }

    public function setDateExport(?\DateTimeImmutable $date_export): self
    {
        $this->date_export = $date_export;
        return $this;
    }
}
