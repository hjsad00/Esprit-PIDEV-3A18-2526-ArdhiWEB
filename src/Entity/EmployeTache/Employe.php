<?php

namespace App\Entity\EmployeTache;

use App\Repository\EmployeTache\EmployeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: EmployeRepository::class)]
#[ORM\Table(name: 'employe')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')]
class Employe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_employe')]
    private ?int $id = null; // @phpstan-ignore property.unusedType

    #[ORM\Column(name: 'nom', length: 100)]
    #[Assert\NotBlank]
    private ?string $nom = null;

    #[ORM\Column(name: 'prenom', length: 100)]
    #[Assert\NotBlank]
    private ?string $prenom = null;

    #[ORM\Column(name: 'email', length: 150, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(name: 'poste', length: 100, nullable: true)]
    private ?string $poste = null;

    #[ORM\Column(name: 'telephone', length: 20)]
    #[Assert\NotBlank]
    #[Assert\Regex(pattern: '/^[0-9]{8}$/')]
    private ?string $telephone = null;

    #[ORM\Column(name: 'actif', options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'id_agriculteur', nullable: true)]
    private ?int $idAgriculteur = null;

    #[ORM\Column(name: 'qr_code_unique', length: 50, nullable: true, unique: true)]
    private ?string $qrCodeUnique = null;

    #[ORM\Column(name: 'photo_path', length: 500, nullable: true)]
    private ?string $photoPath = null;

    // 🔥 NOUVEAUX CHAMPS

    #[ORM\Column(
        name: 'salaire_journalier',
        type: 'decimal',
        precision: 10,
        scale: 3,
        options: ['default' => 40.000]
    )]
    private float $salaireJournalier = 40.0;

    #[ORM\Column(
        name: 'type_contrat',
        type: 'string',
        length: 20,
        options: ['default' => 'CDI']
    )]
    private string $typeContrat = 'CDI';

    #[ORM\Column(
        name: 'date_embauche',
        type: 'date',
        nullable: true
    )]
    private ?\DateTimeInterface $dateEmbauche = null;

    // ── Constructeur ─────────────────────

    public function __construct()
    {
        $this->actif = true;
    }

    // ── Getters / Setters existants ─────

    public function getId(): ?int { return $this->id; }

    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }

    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }

    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }

    public function getPoste(): ?string { return $this->poste; }
    public function setPoste(?string $poste): static { $this->poste = $poste; return $this; }

    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(?string $telephone): static { $this->telephone = $telephone; return $this; }

    public function isActif(): bool { return $this->actif; }
    public function setActif(bool $actif): static { $this->actif = $actif; return $this; }

    public function getIdAgriculteur(): ?int { return $this->idAgriculteur; }
    public function setIdAgriculteur(?int $idAgriculteur): static { $this->idAgriculteur = $idAgriculteur; return $this; }

    public function getQrCodeUnique(): ?string { return $this->qrCodeUnique; }
    public function setQrCodeUnique(?string $qrCodeUnique): static { $this->qrCodeUnique = $qrCodeUnique; return $this; }

    public function getPhotoPath(): ?string { return $this->photoPath; }
    public function setPhotoPath(?string $photoPath): static { $this->photoPath = $photoPath; return $this; }

    // ── 🔥 NOUVEAUX GETTERS / SETTERS ─────

    public function getSalaireJournalier(): float
    {
        return (float) $this->salaireJournalier;
    }

    public function setSalaireJournalier(float $salaire): self
    {
        $this->salaireJournalier = max(0.0, $salaire);
        return $this;
    }

    public function getTypeContrat(): string
    {
        return $this->typeContrat;
    }

    public function setTypeContrat(string $type): self
    {
        $this->typeContrat = $type;
        return $this;
    }

    public function getDateEmbauche(): ?\DateTimeInterface
    {
        return $this->dateEmbauche;
    }

    public function setDateEmbauche(?\DateTimeInterface $date): self
    {
        $this->dateEmbauche = $date;
        return $this;
    }

    public function hasPhoto(): bool
    {
        return !empty($this->photoPath);
    }

    // ── Helpers ─────────────────────────

    public function getNomComplet(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    public function getInitiales(): string
    {
        return strtoupper(substr($this->prenom ?? '', 0, 1) . substr($this->nom ?? '', 0, 1));
    }

    public function genererQrCodeUnique(): string
    {
        $hash = strtoupper(substr(md5($this->id . $this->email . time()), 0, 6));
        return 'EMP_' . $this->id . '_' . $hash;
    }

    public function __toString(): string
    {
        return $this->getNomComplet();
    }
}