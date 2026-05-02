<?php

namespace App\Entity\EmployeTache;

use App\Repository\EmployeTache\TacheRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: TacheRepository::class)]
#[ORM\Table(name: 'tache')]
#[ORM\HasLifecycleCallbacks]
class Tache
{
    // ── Statuts (identiques au desktop) ───────────────────────────────
    public const STATUT_EN_ATTENTE = 'En attente';
    public const STATUT_EN_COURS   = 'En cours';
    public const STATUT_TERMINE    = 'Terminé';
    public const STATUT_VALIDE     = 'Validé';
    public const STATUT_ANNULE     = 'Annulé';

    public const STATUTS = [
        self::STATUT_EN_ATTENTE,
        self::STATUT_EN_COURS,
        self::STATUT_TERMINE,
        self::STATUT_VALIDE,
        self::STATUT_ANNULE,
    ];

    // ── Priorités (identiques au desktop : 1=Basse ... 4=Critique) ───
    public const PRIORITES = [
        1 => 'Basse',
        2 => 'Moyenne',
        3 => 'Haute',
        4 => 'Critique',
    ];

    // ── Catégories (identiques au desktop) ───────────────────────────
    public const CATEGORIES = [
        'Plantation', 'Récolte', 'Irrigation',
        'Fertilisation', 'Maintenance', 'Administratif', 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_tache')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(name: 'titre', length: 200)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire.')]
    #[Assert\Length(max: 200, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $titre = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(name: 'statut', length: 50, options: ['default' => 'En attente'])]
    private string $statut = self::STATUT_EN_ATTENTE;

    #[ORM\Column(name: 'date_debut', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: 'date', nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    // Clé étrangère vers employé (pas de relation ORM pour garder la flexibilité)
    #[ORM\Column(name: 'id_employe', nullable: true)]
    private ?int $idEmploye = null;

    // Multi-tenant
    #[ORM\Column(name: 'id_agriculteur', nullable: true)]
    private ?int $idAgriculteur = null;

    #[ORM\Column(name: 'priorite', nullable: true)]
    private ?int $priorite = 2; // Moyenne par défaut

    #[ORM\Column(name: 'categorie', length: 100, nullable: true)]
    private ?string $categorie = 'Plantation';

    #[ORM\Column(name: 'date_modification', type: 'datetime', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $dateModification = null;

    #[ORM\Column(name: 'google_event_id', length: 255, nullable: true)]
    private ?string $googleEventId = null;

    #[ORM\Column(name: 'type_tache', length: 50, options: ['default' => 'AUTRE'])]
    private string $typeTache = 'AUTRE';

    #[ORM\Column(name: 'google_calendar_event_id', length: 255, nullable: true)]
    private ?string $googleCalendarEventId = null;

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function majDateModification(): void
    {
        $this->dateModification = new \DateTime();
    }

    // ── Getters / Setters ─────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getDescription(): ?string { return $this->description; }
    public function setDescription(?string $d): static { $this->description = $d; return $this; }

    public function getStatut(): string { return $this->statut; }
    public function setStatut(string $s): static { $this->statut = $s; return $this; }

    public function getDateDebut(): ?\DateTimeInterface { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeInterface $d): static { $this->dateDebut = $d; return $this; }

    public function getDateFin(): ?\DateTimeInterface { return $this->dateFin; }
    public function setDateFin(?\DateTimeInterface $d): static { $this->dateFin = $d; return $this; }

    public function getIdEmploye(): ?int { return $this->idEmploye; }
    public function setIdEmploye(?int $i): static { $this->idEmploye = $i; return $this; }

    public function getIdAgriculteur(): ?int { return $this->idAgriculteur; }
    public function setIdAgriculteur(?int $i): static { $this->idAgriculteur = $i; return $this; }

    public function getPriorite(): ?int { return $this->priorite; }
    public function setPriorite(?int $p): static { $this->priorite = $p; return $this; }

    public function getCategorie(): ?string { return $this->categorie; }
    public function setCategorie(?string $c): static { $this->categorie = $c; return $this; }

    public function getDateModification(): ?\DateTimeInterface { return $this->dateModification; }
    public function getGoogleEventId(): ?string { return $this->googleEventId; }
    public function setGoogleEventId(?string $g): static { $this->googleEventId = $g; return $this; }

    public function getTypeTache(): string { return $this->typeTache; }
    public function setTypeTache(string $t): static { $this->typeTache = $t; return $this; }

    public function getGoogleCalendarEventId(): ?string { return $this->googleCalendarEventId; }
    public function setGoogleCalendarEventId(?string $g): static { $this->googleCalendarEventId = $g; return $this; }

    // ── Méthodes utilitaires (identiques au desktop) ──────────────────

    /**
     * Retourne le libellé de la priorité — identique à getPrioriteString() Java
     */
    public function getPrioriteLabel(): string
    {
        return self::PRIORITES[$this->priorite] ?? 'Moyenne';
    }

    /**
     * Retourne la couleur CSS de la priorité — identique au desktop
     */
    public function getPrioriteCouleur(): string
    {
        return match($this->priorite) {
            1 => '#3498db', // Basse — bleu
            2 => '#f39c12', // Moyenne — orange
            3 => '#e74c3c', // Haute — rouge
            4 => '#8e44ad', // Critique — violet
            default => '#f39c12',
        };
    }

    /**
     * Retourne l'icône du statut — identique au desktop
     */
    public function getStatutIcone(): string
    {
        return match($this->statut) {
            self::STATUT_EN_ATTENTE => '⏳',
            self::STATUT_EN_COURS   => '🔄',
            self::STATUT_TERMINE    => '✅',
            self::STATUT_VALIDE     => '✔️',
            self::STATUT_ANNULE     => '❌',
            default => '⏳',
        };
    }

    /**
     * Retourne la couleur CSS du statut
     */
    public function getStatutCouleur(): string
    {
        return match($this->statut) {
            self::STATUT_EN_ATTENTE => '#f39c12',
            self::STATUT_EN_COURS   => '#3498db',
            self::STATUT_TERMINE    => '#27ae60',
            self::STATUT_VALIDE     => '#1abc9c',
            self::STATUT_ANNULE     => '#e74c3c',
            default => '#999',
        };
    }

    /**
     * Vérifie si la tâche est en retard
     */
    public function isEnRetard(): bool
    {
        if ($this->dateFin === null) return false;
        if (in_array($this->statut, [self::STATUT_TERMINE, self::STATUT_VALIDE, self::STATUT_ANNULE])) return false;
        return $this->dateFin < new \DateTime('today');
    }
}