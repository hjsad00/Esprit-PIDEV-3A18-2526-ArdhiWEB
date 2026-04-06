<?php

namespace App\Entity\EmployeTache;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'notification')]
#[ORM\Index(columns: ['id_agriculteur'], name: 'idx_agriculteur')]
#[ORM\Index(columns: ['type'], name: 'idx_type')]
#[ORM\Index(columns: ['lue'], name: 'idx_lue')]
#[ORM\Index(columns: ['date_creation'], name: 'idx_date')]
class Notification
{
    // ── Types ────────────────────────────────────────────────────────────
    public const TYPE_TACHE_RETARD   = 'TACHE_RETARD';
    public const TYPE_TACHE_BLOQUEE  = 'TACHE_BLOQUEE';
    public const TYPE_METEO_POSITIVE = 'METEO_POSITIVE';
    public const TYPE_METEO_PLUIE    = 'METEO_PLUIE';
    public const TYPE_METEO_CHALEUR  = 'METEO_CHALEUR';
    public const TYPE_METEO_VENT     = 'METEO_VENT';
    public const TYPE_METEO_INFO     = 'METEO_INFO';

    // ── Priorités ────────────────────────────────────────────────────────
    public const PRIORITE_CRITICAL = 'CRITICAL';
    public const PRIORITE_WARNING  = 'WARNING';
    public const PRIORITE_INFO     = 'INFO';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_notification', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type;

    #[ORM\Column(type: 'string', length: 20)]
    private string $priorite;

    #[ORM\Column(type: 'string', length: 200)]
    private string $titre;

    #[ORM\Column(type: 'text')]
    private string $message;

    #[ORM\Column(name: 'id_agriculteur', type: 'integer')]
    private int $idAgriculteur;

    #[ORM\Column(name: 'id_tache', type: 'integer', nullable: true)]
    private ?int $idTache = null;

    #[ORM\Column(name: 'id_employe', type: 'integer', nullable: true)]
    private ?int $idEmploye = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $lue = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $archivee = false;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    #[ORM\Column(name: 'date_lecture', type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $dateLecture = null;

    public function __construct()
    {
        $this->dateCreation = new \DateTime();
    }

    // ── Helpers métier ───────────────────────────────────────────────────

    public function getIcone(): string
    {
        return match ($this->type) {
            self::TYPE_TACHE_RETARD   => '⏰',
            self::TYPE_TACHE_BLOQUEE  => '🔒',
            self::TYPE_METEO_POSITIVE => '✅',
            self::TYPE_METEO_PLUIE    => '🌧️',
            self::TYPE_METEO_CHALEUR  => '🌡️',
            self::TYPE_METEO_VENT     => '💨',
            default                   => '🔔',
        };
    }

    public function getCouleurPriorite(): string
    {
        return match ($this->priorite) {
            self::PRIORITE_CRITICAL => '#e74c3c',
            self::PRIORITE_WARNING  => '#f39c12',
            default                 => '#27ae60',
        };
    }

    public function getCssClass(): string
    {
        return match ($this->priorite) {
            self::PRIORITE_CRITICAL => 'notif-critical',
            self::PRIORITE_WARNING  => 'notif-warning',
            default                 => 'notif-info',
        };
    }

    public function getTempsEcoule(): string
    {
        $now  = new \DateTime();
        $diff = $now->diff($this->dateCreation);
        if ($diff->days >= 1)  return $diff->days . ' j';
        if ($diff->h >= 1)     return $diff->h . ' h';
        if ($diff->i >= 1)     return $diff->i . ' min';
        return 'À l\'instant';
    }

    // ── Getters / Setters ────────────────────────────────────────────────

    public function getId(): ?int { return $this->id; }
    public function getType(): string { return $this->type; }
    public function setType(string $type): self { $this->type = $type; return $this; }
    public function getPriorite(): string { return $this->priorite; }
    public function setPriorite(string $priorite): self { $this->priorite = $priorite; return $this; }
    public function getTitre(): string { return $this->titre; }
    public function setTitre(string $titre): self { $this->titre = $titre; return $this; }
    public function getMessage(): string { return $this->message; }
    public function setMessage(string $message): self { $this->message = $message; return $this; }
    public function getIdAgriculteur(): int { return $this->idAgriculteur; }
    public function setIdAgriculteur(int $idAgriculteur): self { $this->idAgriculteur = $idAgriculteur; return $this; }
    public function getIdTache(): ?int { return $this->idTache; }
    public function setIdTache(?int $idTache): self { $this->idTache = $idTache; return $this; }
    public function getIdEmploye(): ?int { return $this->idEmploye; }
    public function setIdEmploye(?int $idEmploye): self { $this->idEmploye = $idEmploye; return $this; }
    public function isLue(): bool { return $this->lue; }
    public function setLue(bool $lue): self { $this->lue = $lue; return $this; }
    public function isArchivee(): bool { return $this->archivee; }
    public function setArchivee(bool $archivee): self { $this->archivee = $archivee; return $this; }
    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }
    public function setDateCreation(\DateTimeInterface $date): self { $this->dateCreation = $date; return $this; }
    public function getDateLecture(): ?\DateTimeInterface { return $this->dateLecture; }
    public function setDateLecture(?\DateTimeInterface $date): self { $this->dateLecture = $date; return $this; }
}