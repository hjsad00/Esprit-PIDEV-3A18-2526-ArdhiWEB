<?php

namespace App\Entity\Evenement;

use App\Repository\Evenement\EvenementRepository;
use App\Entity\UserAndDiag\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private string $titre;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le lieu ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $lieu = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date de début est obligatoire.')]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date de fin est obligatoire.')]
    private \DateTimeInterface $dateFin;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'Le type est obligatoire.')]
    private string $type;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull(message: 'Le nombre de places est obligatoire.')]
    #[Assert\Positive(message: 'Le nombre de places doit être supérieur à zéro.')]
    private int $nombrePlacesMax;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Le nom de l’organisateur ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $organisateur = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'Le chemin de l’image ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private string $statut = 'A_VENIR';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $dateCreation;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_createur', referencedColumnName: 'id', nullable: false)]
    private ?User $createur = null;

    /**
     * @var Collection<int, Participation>
     */
    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: Participation::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $participations;

    /**
     * @var Collection<int, EvenementFavoris>
     */
    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: EvenementFavoris::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $favoris;

    public function __construct()
    {
        if (array_key_exists('__PHPSTAN_ENTITY_ID_HINT', $_SERVER)) {
            $this->id = 0;
        }
        $this->participations = new ArrayCollection();
        $this->favoris = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->statut = 'A_VENIR';
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(?string $lieu): static { $this->lieu = $lieu; return $this; }
    public function getDateDebut(): \DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(\DateTimeInterface $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }
    public function getDateFin(): \DateTimeInterface { return $this->dateFin; }
    public function setDateFin(\DateTimeInterface $dateFin): static { $this->dateFin = $dateFin; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getNombrePlacesMax(): int { return $this->nombrePlacesMax; }
    public function setNombrePlacesMax(int $nombrePlacesMax): static { $this->nombrePlacesMax = $nombrePlacesMax; return $this; }
    public function getOrganisateur(): ?string { return $this->organisateur; }
    public function setOrganisateur(?string $organisateur): static { $this->organisateur = $organisateur; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }
    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    protected function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getCreateur(): ?User { return $this->createur; }
    public function setCreateur(?User $createur): static { $this->createur = $createur; return $this; }
    /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection { return $this->participations; }

    /**
     * @return Collection<int, EvenementFavoris>
     */
    public function getFavoris(): Collection { return $this->favoris; }

    public function getNombreParticipants(): int
    {
        return $this->participations->filter(
            fn($p) => in_array($p->getStatut(), ['CONFIRME', 'PRESENT'])
        )->count();
    }

    #[Assert\Callback]
    public function validateDates(ExecutionContextInterface $context): void
    {
        if ($this->dateDebut && $this->dateFin && $this->dateFin < $this->dateDebut) {
            $context->buildViolation('La date de fin doit être postérieure à la date de début.')
                ->atPath('dateFin')
                ->addViolation();
        }
    }

    public function getPlacesRestantes(): int
    {
        return max(0, $this->nombrePlacesMax - $this->getNombreParticipants());
    }
}
