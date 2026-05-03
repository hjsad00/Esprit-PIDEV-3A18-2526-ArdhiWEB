<?php

namespace App\Entity\MaterielEtMaintenance;

use Doctrine\ORM\EntityManagerInterface;
use App\Repository\MaterielEtMaintenance\MaterielRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MaterielRepository::class)]
#[ORM\Table(name: 'materiel')]
#[ORM\HasLifecycleCallbacks]
class Materiel
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_materiel', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: \App\Entity\UserAndDiag\User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    private ?\App\Entity\UserAndDiag\User $user = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "Oups, n'oubliez pas le nom.")]
    private ?string $nom = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\LessThanOrEqual('today', message: "Tu ne peux pas mettre une date d'achat au futur.")]
    private ?\DateTimeInterface $dateAchat = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $derniereMaintenance = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateProchaineMaintenance = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: "Veuillez choisir un type de matériel.")]
    #[Assert\Choice(choices: ['Tracteur', 'Moissonneuse', 'Semoir', 'Pulvérisateur', 'Charrue', 'Herse', 'Autre'], message: "Type invalide.")]
    private ?string $type = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\NotBlank(message: "Veuillez indiquer l'état du matériel.")]
    #[Assert\Choice(choices: ['Neuf', 'Bon', 'Moyen', 'En panne', 'En maintenance'], message: "État invalide.")]
    private ?string $etat = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $prochaine_maintenance_alerte = null;

    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $qrCodeToken = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $qrCodePath = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(length: 50)]
    private string $statut = 'en_service';

    #[ORM\Column(type: Types::INTEGER)]
    private int $heuresUtilisation = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $seuilMaintenanceHeures = 500;

    #[ORM\Column(type: Types::INTEGER)]
    private int $derniereMaintenanceHeures = 0;

    #[ORM\OneToMany(mappedBy: 'materiel', targetEntity: AlerteTechnicien::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $alerteTechniciens;

    #[ORM\OneToMany(mappedBy: 'materiel', targetEntity: Maintenance::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $maintenances;

    public function __construct()
    {
        $this->maintenances = new ArrayCollection();
        $this->alerteTechniciens = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?\App\Entity\UserAndDiag\User
    {
        return $this->user;
    }

    public function setUser(?\App\Entity\UserAndDiag\User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): self
    {
        $this->nom = $nom;

        return $this;
    }

    public function getDateAchat(): ?\DateTimeInterface
    {
        return $this->dateAchat;
    }

    public function setDateAchat(?\DateTimeInterface $dateAchat): self
    {
        $this->dateAchat = $dateAchat;

        return $this;
    }

    public function calculerProchaineMaintenance(): void
    {
        $baseDate = $this->getDerniereMaintenance() ?: $this->getDateAchat();
        
        if (!$baseDate) {
            $baseDate = new \DateTime();
        } else {
            $baseDate = clone $baseDate;
        }

        $baseDate->modify('+6 months');
        $this->setDateProchaineMaintenance($baseDate);
    }

    public function getSituationMaintenance(): string
    {
        // S'il y a déjà une intervention en cours/planifiée, on indique "planifiee" sans marquer de retard
        foreach ($this->getMaintenances() as $m) {
            if (in_array($m->getStatutMaintenance(), ['planifiee', 'en_attente', 'en_cours'])) {
                return 'planifiee';
            }
        }

        if (!$this->dateProchaineMaintenance) {
            return 'non_planifie';
        }

        $now = new \DateTime();
        $now->setTime(0, 0, 0);
        $prochaine = clone $this->dateProchaineMaintenance;
        $prochaine->setTime(0, 0, 0);

        if ($prochaine < $now) {
            return 'en_retard';
        }

        $diff = $now->diff($prochaine)->days;
        if ($diff <= 7) {
            return 'bientot';
        }

        return 'ok';
    }

    public function getDerniereMaintenance(): ?\DateTimeInterface
    {
        return $this->derniereMaintenance;
    }

    public function setDerniereMaintenance(?\DateTimeInterface $derniereMaintenance): self
    {
        $this->derniereMaintenance = $derniereMaintenance;

        return $this;
    }

    public function getDateProchaineMaintenance(): ?\DateTimeInterface
    {
        return $this->dateProchaineMaintenance;
    }

    public function setDateProchaineMaintenance(?\DateTimeInterface $dateProchaineMaintenance): self
    {
        $this->dateProchaineMaintenance = $dateProchaineMaintenance;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(?string $etat): self
    {
        $this->etat = $etat;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    /**
     * @return Collection<int, Maintenance>
     */
    public function getMaintenances(): Collection
    {
        return $this->maintenances;
    }

    public function addMaintenance(Maintenance $maintenance): self
    {
        if (!$this->maintenances->contains($maintenance)) {
            $this->maintenances->add($maintenance);
            $maintenance->setMateriel($this);
        }

        return $this;
    }

    public function removeMaintenance(Maintenance $maintenance): self
    {
        if ($this->maintenances->removeElement($maintenance)) {
            // set the owning side to null (unless already changed)
            if ($maintenance->getMateriel() === $this) {
                $maintenance->setMateriel(null);
            }
        }

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

    public function getQrCodeToken(): ?string
    {
        return $this->qrCodeToken;
    }

    public function setQrCodeToken(?string $qrCodeToken): self
    {
        $this->qrCodeToken = $qrCodeToken;
        return $this;
    }

    public function getQrCodePath(): ?string
    {
        return $this->qrCodePath;
    }

    public function setQrCodePath(?string $qrCodePath): self
    {
        $this->qrCodePath = $qrCodePath;
        return $this;
    }

    #[ORM\PrePersist]
    public function generateToken(): void
    {
        if (null === $this->qrCodeToken) {
            $this->qrCodeToken = bin2hex(random_bytes(16));
        }
    }

    public function getHeuresUtilisation(): int
    {
        return $this->heuresUtilisation;
    }

    public function setHeuresUtilisation(int $heuresUtilisation): self
    {
        $this->heuresUtilisation = $heuresUtilisation;
        return $this;
    }

    public function getSeuilMaintenanceHeures(): int
    {
        return $this->seuilMaintenanceHeures;
    }

    public function setSeuilMaintenanceHeures(int $seuilMaintenanceHeures): self
    {
        $this->seuilMaintenanceHeures = $seuilMaintenanceHeures;
        return $this;
    }

    public function initialiserSeuilParDefaut(): void
    {
        $this->seuilMaintenanceHeures = match ($this->type) {
            'Tracteur' => 500,
            'Moissonneuse' => 300,
            'Pulvérisateur' => 250,
            'Semoir' => 200,
            'Charrue', 'Herse' => 400,
            default => 500,
        };
    }

    public function getDerniereMaintenanceHeures(): int
    {
        return $this->derniereMaintenanceHeures;
    }

    public function setDerniereMaintenanceHeures(int $derniereMaintenanceHeures): self
    {
        $this->derniereMaintenanceHeures = $derniereMaintenanceHeures;
        return $this;
    }

    /**
     * @return Collection<int, AlerteTechnicien>
     */
    public function getAlerteTechniciens(): Collection
    {
        return $this->alerteTechniciens;
    }

    public function addAlerteTechnicien(AlerteTechnicien $alerteTechnicien): static
    {
        if (!$this->alerteTechniciens->contains($alerteTechnicien)) {
            $this->alerteTechniciens->add($alerteTechnicien);
            $alerteTechnicien->setMateriel($this);
        }

        return $this;
    }

    public function removeAlerteTechnicien(AlerteTechnicien $alerteTechnicien): static
    {
        if ($this->alerteTechniciens->removeElement($alerteTechnicien)) {
            // set the owning side to null (unless already changed)
            if ($alerteTechnicien->getMateriel() === $this) {
                $alerteTechnicien->setMateriel(null);
            }
        }

        return $this;
    }

    /**
     * Récupère l'agriculteur propriétaire (User) à partir du userId stocké.
     * On passe par l'EntityManager pour charger l'entité User associée.
     */
    public function getAgriculteur(EntityManagerInterface $em): ?\App\Entity\UserAndDiag\User
    {
        return $this->user;
    }
}
