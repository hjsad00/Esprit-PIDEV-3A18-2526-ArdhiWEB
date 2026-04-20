<?php

namespace App\Entity\EmployeTache;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'pointage')]
#[ORM\Index(columns: ['id_employe', 'date_heure'], name: 'idx_employe_date')]
#[ORM\Index(columns: ['id_agriculteur'], name: 'idx_agriculteur')]
class Pointage
{
    public const TYPE_ARRIVEE = 'ARRIVEE';
    public const TYPE_DEPART  = 'DEPART';
    public const SOURCE_GPS   = 'GPS';
    public const SOURCE_QR    = 'QR';
    public const SOURCE_MANUEL = 'MANUEL';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_pointage', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'id_employe', type: 'integer')]
    private int $idEmploye;

    #[ORM\Column(name: 'id_agriculteur', type: 'integer')]
    private int $idAgriculteur;

    #[ORM\Column(type: 'string', length: 10, columnDefinition: "ENUM('ARRIVEE','DEPART')")]
    private string $type = self::TYPE_ARRIVEE;

    #[ORM\Column(name: 'date_heure', type: 'datetime')]
    private \DateTimeInterface $dateHeure;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(name: 'distance_ferme', type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?float $distanceFerme = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $valide = false;

    #[ORM\Column(type: 'string', length: 20, options: ['default' => 'GPS'])]
    private string $source = self::SOURCE_GPS;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private \DateTimeInterface $dateCreation;

    public function __construct()
    {
        $this->dateHeure   = new \DateTime();
        $this->dateCreation = new \DateTime();
    }

    // Getters/Setters
    public function getId(): ?int { return $this->id; }
    public function getIdEmploye(): int { return $this->idEmploye; }
    public function setIdEmploye(int $v): self { $this->idEmploye = $v; return $this; }
    public function getIdAgriculteur(): int { return $this->idAgriculteur; }
    public function setIdAgriculteur(int $v): self { $this->idAgriculteur = $v; return $this; }
    public function getType(): string { return $this->type; }
    public function setType(string $v): self { $this->type = $v; return $this; }
    public function getDateHeure(): \DateTimeInterface { return $this->dateHeure; }
    public function setDateHeure(\DateTimeInterface $v): self { $this->dateHeure = $v; return $this; }
    public function getLatitude(): ?float { return $this->latitude; }
    public function setLatitude(?float $v): self { $this->latitude = $v; return $this; }
    public function getLongitude(): ?float { return $this->longitude; }
    public function setLongitude(?float $v): self { $this->longitude = $v; return $this; }
    public function getDistanceFerme(): ?float { return $this->distanceFerme; }
    public function setDistanceFerme(?float $v): self { $this->distanceFerme = $v; return $this; }
    public function isValide(): bool { return $this->valide; }
    public function setValide(bool $v): self { $this->valide = $v; return $this; }
    public function getSource(): string { return $this->source; }
    public function setSource(string $v): self { $this->source = $v; return $this; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $v): self { $this->commentaire = $v; return $this; }
    public function getDateCreation(): \DateTimeInterface { return $this->dateCreation; }

    public function getHeureFormatee(): string
    {
        return $this->dateHeure->format('H:i');
    }

    public function getIcone(): string
    {
        return $this->type === self::TYPE_ARRIVEE ? '→' : '←';
    }

    public function getStatutLabel(): string
    {
        if (!$this->valide) return 'Hors site';
        return $this->type === self::TYPE_ARRIVEE ? 'Arrivée validée' : 'Départ validé';
    }
}