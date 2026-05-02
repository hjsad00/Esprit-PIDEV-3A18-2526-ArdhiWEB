<?php

namespace App\Entity\Marketplace;

use App\Entity\UserAndDiag\User;
use App\Repository\Marketplace\ProduitsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: ProduitsRepository::class)]
#[ORM\Table(name: 'produits')]
class Produits
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idProduit')]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom du produit est obligatoire.')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private string $nom;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotBlank(message: 'Le prix est obligatoire.')]
    #[Assert\GreaterThan(value: 0.1, message: 'Le prix doit être supérieur à 0.1 DT.')]
    private float $prix;

    #[ORM\Column(name: 'quantiteStock')]
    #[Assert\NotBlank(message: 'La quantité en stock est obligatoire.')]
    #[Assert\GreaterThanOrEqual(value: 1, message: 'Le stock doit être supérieur à 0.')]
    private int $quantiteStock;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire.')]
    private ?string $categorie = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_user', referencedColumnName: 'id', nullable: false)]
    private ?User $user = null;

    #[ORM\Column(name: 'uniteMesure', type: Types::STRING, length: 10, columnDefinition: "ENUM('Kg','L','Piece') NOT NULL")]
    #[Assert\NotBlank(message: "L'unité de mesure est obligatoire.")]
    #[Assert\Choice(
        choices: ['Kg', 'L', 'Piece'],
        message: "L'unité de mesure doit être l'une des valeurs suivantes : Kg, L, Piece."
    )]
    private string $uniteMesure;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    #[ORM\Column(type: Types::FLOAT, options: ["default" => 0])]
    private float $remise = 0.0;

    #[ORM\Column(name: 'typeRemise', length: 20, nullable: true)]
    private ?string $typeRemise = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ["default" => true])]
    private bool $visible = true;

    #[ORM\Column(name: 'visible_admin', type: Types::BOOLEAN, options: ["default" => true])]
    private bool $visibleAdmin = true;

    #[ORM\ManyToOne(targetEntity: \App\Entity\MaterielEtMaintenance\Materiel::class)]
    #[ORM\JoinColumn(name: 'materiel_id', referencedColumnName: 'id_materiel', nullable: true)]
    private ?\App\Entity\MaterielEtMaintenance\Materiel $materiel = null;

    // Virtual fields hydrated at runtime (not persisted)
    private float $averageRating = 0.0;
    private int $reviewsCount = 0;

    // ==================== GETTERS & SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrix(): float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    public function getQuantiteStock(): int
    {
        return $this->quantiteStock;
    }

    public function setQuantiteStock(int $quantiteStock): static
    {
        $this->quantiteStock = $quantiteStock;
        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getUniteMesure(): string
    {
        return $this->uniteMesure;
    }

    public function setUniteMesure(string $uniteMesure): static
    {
        $this->uniteMesure = $uniteMesure;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    public function getRemise(): float
    {
        return $this->remise;
    }

    public function setRemise(?float $remise): static
    {
        $this->remise = $remise ?? 0.0;
        return $this;
    }

    public function getTypeRemise(): ?string
    {
        return $this->typeRemise;
    }

    public function setTypeRemise(?string $typeRemise): static
    {
        $this->typeRemise = $typeRemise;
        return $this;
    }

    public function isVisible(): bool
    {
        return $this->visible;
    }

    public function setVisible(bool $visible): static
    {
        $this->visible = $visible;
        return $this;
    }

    public function isVisibleAdmin(): bool
    {
        return $this->visibleAdmin;
    }

    public function setVisibleAdmin(bool $visibleAdmin): static
    {
        $this->visibleAdmin = $visibleAdmin;
        return $this;
    }

    public function getAverageRating(): float
    {
        return $this->averageRating;
    }

    public function setAverageRating(float $averageRating): static
    {
        $this->averageRating = max(0.0, min(5.0, $averageRating));
        return $this;
    }

    public function getReviewsCount(): int
    {
        return $this->reviewsCount;
    }

    public function setReviewsCount(int $reviewsCount): static
    {
        $this->reviewsCount = max(0, $reviewsCount);
        return $this;
    }

    public function getMateriel(): ?\App\Entity\MaterielEtMaintenance\Materiel
    {
        return $this->materiel;
    }

    public function setMateriel(?\App\Entity\MaterielEtMaintenance\Materiel $materiel): static
    {
        $this->materiel = $materiel;
        return $this;
    }

    // ==================== VALIDATION CALLBACK (Remise) ====================


    /**
     * Validation conditionnelle de la remise selon son type.
     * - POURCENTAGE : doit être entre 1 et 100.
     * - FIXE        : doit être entre 0.1 DT et le prix du produit.
     */
    #[Assert\Callback]
    public function validateRemise(ExecutionContextInterface $context): void
    {
        if ($this->typeRemise === null || $this->typeRemise === 'AUCUNE') {
            return; // Pas de remise : rien à valider.
        }

        if ($this->typeRemise === 'POURCENTAGE') {
            if ($this->remise === null || $this->remise < 1 || $this->remise > 100) {
                $context->buildViolation('La remise en pourcentage doit être comprise entre 1 et 100.')
                    ->atPath('remise')
                    ->addViolation();
            }
        } elseif ($this->typeRemise === 'FIXE') {
            if ($this->remise === null || $this->remise < 0.1) {
                $context->buildViolation('La remise fixe doit être d\'au moins 0.1 DT.')
                    ->atPath('remise')
                    ->addViolation();
            } elseif ($this->prix !== null && $this->remise >= $this->prix) {
                $context->buildViolation('La remise fixe ({{ remise }} DT) ne peut pas être supérieure ou égale au prix du produit ({{ prix }} DT).')
                    ->setParameter('{{ remise }}', (string) $this->remise)
                    ->setParameter('{{ prix }}', (string) $this->prix)
                    ->atPath('remise')
                    ->addViolation();
            }
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * Calcule le prix final après remise.
     */
    public function getPrixFinal(): float
    {
        $prix = $this->prix ?? 0.0;
        $remise = $this->remise ?? 0.0;

        if ($remise <= 0 || $this->typeRemise === null) {
            return (float) $prix;
        }

        if ($this->typeRemise === 'POURCENTAGE') {
            return (float) ($prix * (1 - $remise / 100));
        }

        // Remise fixe
        return (float) max(0.0, $prix - $remise);
    }
}