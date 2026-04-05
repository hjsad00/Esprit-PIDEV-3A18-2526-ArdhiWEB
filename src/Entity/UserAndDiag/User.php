<?php

namespace App\Entity\UserAndDiag;

use App\Repository\UserAndDiag\UserRepository;
use App\Entity\Parcelles_Cultures\Parcelle;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 255, columnDefinition: "ENUM('ADMIN','AGRICULTEUR','CLIENT','AGRONOME') NOT NULL DEFAULT 'AGRICULTEUR'")]
    private ?string $role = 'AGRICULTEUR';

    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(options: ["default" => 0])]
    private ?int $points = 0;

    #[ORM\Column(options: ["default" => 1])]
    private ?int $level = 1;

    #[ORM\Column(options: ["default" => false])]
    private ?bool $two_factor_enabled = false;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $two_factor_code = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $two_factor_expires_at = null;


    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $fingerprint_signature = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $face_signature = null;

    #[ORM\Column(type: Types::FLOAT, options: ["default" => 0])]
    private ?float $points_fidelite = 0;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $reset_password_code = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reset_password_expires_at = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    /**
     * @var Collection<int, Parcelle>
     */
    #[ORM\OneToMany(targetEntity: Parcelle::class, mappedBy: 'agriculteur', cascade: ['remove'])]
    private Collection $parcelles;

    public function __construct()
    {
        $this->parcelles = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = [];
        if ($this->role) {
            $roles[] = 'ROLE_' . strtoupper($this->role);
        }
        $roles[] = 'ROLE_USER';
        return array_unique($roles);
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;
        return $this;
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

    public function getPoints(): ?int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;
        return $this;
    }

    public function getLevel(): ?int
    {
        return $this->level;
    }

    public function setLevel(int $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function isTwoFactorEnabled(): ?bool
    {
        return $this->two_factor_enabled;
    }

    public function setTwoFactorEnabled(bool $two_factor_enabled): static
    {
        $this->two_factor_enabled = $two_factor_enabled;
        return $this;
    }

    public function getTwoFactorCode(): ?string
    {
        return $this->two_factor_code;
    }

    public function setTwoFactorCode(?string $two_factor_code): static
    {
        $this->two_factor_code = $two_factor_code;
        return $this;
    }

    public function getTwoFactorExpiresAt(): ?\DateTimeInterface
    {
        return $this->two_factor_expires_at;
    }

    public function setTwoFactorExpiresAt(?\DateTimeInterface $two_factor_expires_at): static
    {
        $this->two_factor_expires_at = $two_factor_expires_at;
        return $this;
    }



    public function getFingerprintSignature(): ?string
    {
        return $this->fingerprint_signature;
    }

    public function setFingerprintSignature(?string $fingerprint_signature): static
    {
        $this->fingerprint_signature = $fingerprint_signature;
        return $this;
    }

    public function getFaceSignature(): ?string
    {
        return $this->face_signature;
    }

    public function setFaceSignature(?string $face_signature): static
    {
        $this->face_signature = $face_signature;
        return $this;
    }

    public function getResetPasswordCode(): ?string
    {
        return $this->reset_password_code;
    }

    public function setResetPasswordCode(?string $reset_password_code): static
    {
        $this->reset_password_code = $reset_password_code;
        return $this;
    }

    public function getResetPasswordExpiresAt(): ?\DateTimeInterface
    {
        return $this->reset_password_expires_at;
    }

    public function setResetPasswordExpiresAt(?\DateTimeInterface $reset_password_expires_at): static
    {
        $this->reset_password_expires_at = $reset_password_expires_at;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getPointsFidelite(): ?float
    {
        return $this->points_fidelite;
    }

    public function setPointsFidelite(float $points_fidelite): static
    {
        $this->points_fidelite = $points_fidelite;
        return $this;
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    /**
     * @return Collection<int, Parcelle>
     */
    public function getParcelles(): Collection
    {
        return $this->parcelles;
    }

    public function addParcelle(Parcelle $parcelle): static
    {
        if (!$this->parcelles->contains($parcelle)) {
            $this->parcelles->add($parcelle);
            $parcelle->setAgriculteur($this);
        }
        return $this;
    }

    public function removeParcelle(Parcelle $parcelle): static
    {
        if ($this->parcelles->removeElement($parcelle)) {
            if ($parcelle->getAgriculteur() === $this) {
                $parcelle->setAgriculteur(null);
            }
        }
        return $this;
    }
}
