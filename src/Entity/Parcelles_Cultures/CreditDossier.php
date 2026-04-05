<?php

namespace App\Entity\Parcelles_Cultures;

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

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: 'La durée en années est obligatoire')]
    #[Assert\GreaterThan(value: 0, message: 'La durée doit être > 0')]
    private ?int $duree_annees = null;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2)]
    private ?string $montant_pret_max = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $capacite_remboursement = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $score_risque = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $niveau_risque = 'modere';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $score_rentabilite = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $score_stabilite_climat = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $score_diversification = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $score_historique = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $recommandations = null;

    #[ORM\Column(type: 'string', length: 50, options: ['default' => 'draft'])]
    private string $statut = 'draft';

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\ManyToOne(targetEntity: Parcelle::class, inversedBy: 'creditDossiers')]
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

    public function getDureeAnnees(): ?int
    {
        return $this->duree_annees;
    }

    public function setDureeAnnees(int $duree_annees): static
    {
        $this->duree_annees = $duree_annees;
        return $this;
    }

    public function getMontantPretMax(): ?string
    {
        return $this->montant_pret_max;
    }

    public function setMontantPretMax(?string $montant_pret_max): static
    {
        $this->montant_pret_max = $montant_pret_max;
        return $this;
    }

    public function getCapaciteRemboursement(): ?string
    {
        return $this->capacite_remboursement;
    }

    public function setCapaciteRemboursement(?string $capacite_remboursement): static
    {
        $this->capacite_remboursement = $capacite_remboursement;
        return $this;
    }

    public function getScoreRisque(): ?string
    {
        return $this->score_risque;
    }

    public function setScoreRisque(?string $score_risque): static
    {
        $this->score_risque = $score_risque;
        return $this;
    }

    public function getNiveauRisque(): string
    {
        return $this->niveau_risque;
    }

    public function setNiveauRisque(string $niveau_risque): static
    {
        $this->niveau_risque = $niveau_risque;
        return $this;
    }

    public function getScoreRentabilite(): ?string
    {
        return $this->score_rentabilite;
    }

    public function setScoreRentabilite(?string $score_rentabilite): static
    {
        $this->score_rentabilite = $score_rentabilite;
        return $this;
    }

    public function getScoreStabiliteClimat(): ?string
    {
        return $this->score_stabilite_climat;
    }

    public function setScoreStabiliteClimat(?string $score_stabilite_climat): static
    {
        $this->score_stabilite_climat = $score_stabilite_climat;
        return $this;
    }

    public function getScoreDiversification(): ?string
    {
        return $this->score_diversification;
    }

    public function setScoreDiversification(?string $score_diversification): static
    {
        $this->score_diversification = $score_diversification;
        return $this;
    }

    public function getScoreHistorique(): ?string
    {
        return $this->score_historique;
    }

    public function setScoreHistorique(?string $score_historique): static
    {
        $this->score_historique = $score_historique;
        return $this;
    }

    public function getRecommandations(): ?string
    {
        return $this->recommandations;
    }

    public function setRecommandations(?string $recommandations): static
    {
        $this->recommandations = $recommandations;
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
