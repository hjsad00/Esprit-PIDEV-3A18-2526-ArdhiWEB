<?php

namespace App\Entity\Evenement;

use App\Repository\Evenement\EvenementRepository;
use App\Entity\UserAndDiag\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
#[ORM\Table(name: 'evenement')]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $lieu = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $nombrePlacesMax = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $organisateur = null;

    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    private ?string $imageUrl = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    private ?string $statut = 'A_VENIR';

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_createur', referencedColumnName: 'id', nullable: false)]
    private ?User $createur = null;

    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: Participation::class, cascade: ['remove'])]
    private Collection $participations;

    #[ORM\OneToMany(mappedBy: 'evenement', targetEntity: EvenementFavoris::class, cascade: ['remove'])]
    private Collection $favoris;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
        $this->favoris = new ArrayCollection();
        $this->dateCreation = new \DateTime();
        $this->statut = 'A_VENIR';
    }

    public function getId(): ?int { return $this->id; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $description): static { $this->description = $description; return $this; }
    public function getLieu(): ?string { return $this->lieu; }
    public function setLieu(?string $lieu): static { $this->lieu = $lieu; return $this; }
    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeInterface $dateDebut): static { $this->dateDebut = $dateDebut; return $this; }
    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function setDateFin(?\DateTimeInterface $dateFin): static { $this->dateFin = $dateFin; return $this; }
    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }
    public function getNombrePlacesMax(): ?int { return $this->nombrePlacesMax; }
    public function setNombrePlacesMax(int $nombrePlacesMax): static { $this->nombrePlacesMax = $nombrePlacesMax; return $this; }
    public function getOrganisateur(): ?string { return $this->organisateur; }
    public function setOrganisateur(?string $organisateur): static { $this->organisateur = $organisateur; return $this; }
    public function getImageUrl(): ?string { return $this->imageUrl; }
    public function setImageUrl(?string $imageUrl): static { $this->imageUrl = $imageUrl; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getDateCreation(): ?\DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $dateCreation): static { $this->dateCreation = $dateCreation; return $this; }
    public function getCreateur(): ?User { return $this->createur; }
    public function setCreateur(?User $createur): static { $this->createur = $createur; return $this; }
    public function getParticipations(): Collection { return $this->participations; }
    public function getFavoris(): Collection { return $this->favoris; }

    public function getNombreParticipants(): int
    {
        return $this->participations->filter(
            fn($p) => in_array($p->getStatut(), ['CONFIRME', 'PRESENT'])
        )->count();
    }

    public function getPlacesRestantes(): int
    {
        return max(0, $this->nombrePlacesMax - $this->getNombreParticipants());
    }
}
