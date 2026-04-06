<?php

namespace App\Entity\EmployeTache;

use App\Repository\EmployeTache\EmployeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

#[ORM\Entity(repositoryClass: EmployeRepository::class)]
#[ORM\Table(name: 'employe')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé.')] // controle saisie de mail
class Employe
{
    #[ORM\Id]
    #[ORM\GeneratedValue]// (AUTO_INCREMENT)

    #[ORM\Column(name: 'id_employe')] // nom exacte dans la base
    private ?int $id = null;

    #[ORM\Column(name: 'nom', length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    #[ORM\Column(name: 'prenom', length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')] //vérifiées avant d'enregistrer 
    #[Assert\Length(max: 100)]
    private ?string $prenom = null;

    #[ORM\Column(name: 'email', length: 150, unique: true)]
    #[Assert\NotBlank(message: "L'email est obligatoire.")]
    #[Assert\Email(message: "L'email {{ value }} n'est pas valide.")]
    private ?string $email = null;

    #[ORM\Column(name: 'poste', length: 100, nullable: true)]
    private ?string $poste = null;

    #[ORM\Column(name: 'telephone', length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(name: 'actif', options: ['default' => true])] //au niveau bdd
    private bool $actif = true; //au niveau PHP

    // Clé étrangère vers l'agriculteur (pas de relation ORM — géré par UserAndDiag)
    #[ORM\Column(name: 'id_agriculteur', nullable: true)]
    private ?int $idAgriculteur = null;

    // QR Code unique : ex "EMP_30_A4B3C2"
    #[ORM\Column(name: 'qr_code_unique', length: 50, nullable: true, unique: true)]
    private ?string $qrCodeUnique = null;

    // Chemin vers la photo de profil uploadée
    #[ORM\Column(name: 'photo_path', length: 500, nullable: true)]
    private ?string $photoPath = null;

    // ── Constructeur ──────────────────────────────────────────────────────

    public function __construct()
    {
        $this->actif = true;
    }

    // ── Getters / Setters ─────────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPoste(): ?string
    {
        return $this->poste;
    }

    public function setPoste(?string $poste): static
    {
        $this->poste = $poste;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function isActif(): bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function getIdAgriculteur(): ?int
    {
        return $this->idAgriculteur;
    }

    public function setIdAgriculteur(?int $idAgriculteur): static
    {
        $this->idAgriculteur = $idAgriculteur;
        return $this;
    }

    public function getQrCodeUnique(): ?string
    {
        return $this->qrCodeUnique;
    }

    public function setQrCodeUnique(?string $qrCodeUnique): static
    {
        $this->qrCodeUnique = $qrCodeUnique;
        return $this;
    }

    public function getPhotoPath(): ?string
    {
        return $this->photoPath;
    }

    public function setPhotoPath(?string $photoPath): static
    {
        $this->photoPath = $photoPath;
        return $this;
    }

    // ── Méthodes utilitaires ──────────────────────────────────────────────

    /**
     * Retourne true si une photo est définie
     */
    public function hasPhoto(): bool
    {
        return $this->photoPath !== null && $this->photoPath !== '';
    }

    /**
     * Retourne "Prénom Nom"
     */
    public function getNomComplet(): string
    {
        return trim($this->prenom . ' ' . $this->nom);
    }

    /**
     * Retourne les initiales pour l'avatar — ex: "AB" pour "Asma Benattia"
     */
    public function getInitiales(): string
    {
        $p = $this->prenom ? mb_strtoupper(mb_substr($this->prenom, 0, 1)) : '?';
        $n = $this->nom    ? mb_strtoupper(mb_substr($this->nom,    0, 1)) : '?';
        return $p . $n;
    }

    /**
     * Génère un QR code unique — à appeler après le premier flush (ID disponible)
     * Format : EMP_{id}_{6 chars hex}
     */
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