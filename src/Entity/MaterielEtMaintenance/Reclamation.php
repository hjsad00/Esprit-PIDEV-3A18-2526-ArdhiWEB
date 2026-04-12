<?php

namespace App\Entity\MaterielEtMaintenance;

use App\Entity\UserAndDiag\User;
use App\Repository\MaterielEtMaintenance\ReclamationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReclamationRepository::class)]
#[ORM\Table(name: 'reclamation_materiel')]
class Reclamation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Materiel::class)]
    #[ORM\JoinColumn(name: 'materiel_id', referencedColumnName: 'id_materiel', nullable: true, onDelete: 'SET NULL')]
    private ?Materiel $materiel = null;

    #[ORM\Column(name: 'sujet', type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'Le sujet est obligatoire.')]
    #[Assert\Length(max: 255, maxMessage: 'Le sujet ne peut pas dépasser 255 caractères.')]
    private ?string $sujet = null;

    #[ORM\Column(name: 'description', type: 'text')]
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    private ?string $description = null;

    #[ORM\Column(name: 'urgence', type: 'string', length: 20, options: ['default' => 'normale'])]
    #[Assert\Choice(choices: ['normale', 'urgente'], message: 'Urgence invalide.')]
    private string $urgence = 'normale';

    #[ORM\Column(name: 'statut', type: 'string', length: 30, options: ['default' => 'en_attente'])]
    private string $statut = 'en_attente';

    #[ORM\Column(name: 'commentaire_admin', type: 'text', nullable: true)]
    private ?string $commentaireAdmin = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'updated_at', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): self { $this->user = $user; return $this; }

    public function getMateriel(): ?Materiel { return $this->materiel; }
    public function setMateriel(?Materiel $materiel): self { $this->materiel = $materiel; return $this; }

    public function getSujet(): ?string { return $this->sujet; }
    public function setSujet(string $sujet): self { $this->sujet = $sujet; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): self { $this->description = $description; return $this; }

    public function getUrgence(): string { return $this->urgence; }
    public function setUrgence(string $urgence): self { $this->urgence = $urgence; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $statut): self { $this->statut = $statut; $this->updatedAt = new \DateTime(); return $this; }

    public function getCommentaireAdmin(): ?string { return $this->commentaireAdmin; }
    public function setCommentaireAdmin(?string $c): self { $this->commentaireAdmin = $c; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function getUpdatedAt(): ?\DateTimeInterface { return $this->updatedAt; }
}
