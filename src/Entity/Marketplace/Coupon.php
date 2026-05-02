<?php

namespace App\Entity\Marketplace;

use App\Repository\Marketplace\CouponRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: CouponRepository::class)]
#[ORM\Table(name: 'coupon')]
class Coupon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCoupon')]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le code promo est obligatoire.')]
    #[Assert\Length(min: 3, max: 50, minMessage: 'Le code doit faire au moins {{ limit }} caractères.')]
    private string $code;

    #[ORM\Column(name: 'typeReduction', type: Types::STRING, length: 20, columnDefinition: "ENUM('POURCENTAGE', 'FIXE') NOT NULL")]
    #[Assert\NotBlank(message: 'Le type de réduction est obligatoire.')]
    #[Assert\Choice(choices: ['POURCENTAGE', 'FIXE'], message: 'Type de réduction invalide.')]
    private string $typeReduction;

    #[ORM\Column(type: Types::FLOAT)]
    #[Assert\NotBlank(message: 'La valeur est obligatoire.')]
    #[Assert\GreaterThan(value: 0, message: 'La valeur doit être supérieure à 0.')]
    private float $valeur;

    #[ORM\Column(name: 'dateDebut', type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire.')]
    private \DateTimeInterface $dateDebut;

    #[ORM\Column(name: 'dateFin', type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire.')]
    private \DateTimeInterface $dateFin;

    #[ORM\Column(name: 'utilisationMax', options: ['default' => 0])]
    private int $utilisationMax = 0;

    #[ORM\Column(name: 'utilisationActuelle', options: ['default' => 0])]
    private int $utilisationActuelle = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'montantMin', type: Types::FLOAT, options: ['default' => 0])]
    private float $montantMin = 0;

    #[ORM\Column(name: 'limiteParUser', options: ['default' => 1])]
    private int $limiteParUser = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getTypeReduction(): string
    {
        return $this->typeReduction;
    }

    public function setTypeReduction(string $typeReduction): static
    {
        $this->typeReduction = $typeReduction;
        return $this;
    }

    public function getValeur(): float
    {
        return $this->valeur;
    }

    public function setValeur(float $valeur): static
    {
        $this->valeur = $valeur;
        return $this;
    }

    public function getDateDebut(): \DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): \DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getUtilisationMax(): int
    {
        return $this->utilisationMax;
    }

    public function setUtilisationMax(int $utilisationMax): static
    {
        $this->utilisationMax = $utilisationMax;
        return $this;
    }

    public function getUtilisationActuelle(): int
    {
        return $this->utilisationActuelle;
    }

    public function setUtilisationActuelle(int $utilisationActuelle): static
    {
        $this->utilisationActuelle = $utilisationActuelle;
        return $this;
    }

    public function isActif(): ?bool
    {
        return $this->actif;
    }

    public function setActif(bool $actif): static
    {
        $this->actif = $actif;
        return $this;
    }

    public function getMontantMin(): ?float
    {
        return $this->montantMin;
    }

    public function setMontantMin(float $montantMin): static
    {
        $this->montantMin = $montantMin;
        return $this;
    }

    public function getLimiteParUser(): int
    {
        return $this->limiteParUser;
    }

    public function setLimiteParUser(int $limiteParUser): static
    {
        $this->limiteParUser = $limiteParUser;
        return $this;
    }

    /**
     * Validation personnalisée pour les dates et les pourcentages.
     */
    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        // 1. Validation des dates
        if ($this->dateDebut && $this->dateFin) {
            if ($this->dateFin < $this->dateDebut) {
                $context->buildViolation('La date de fin doit être postérieure ou égale à la date de début.')
                    ->atPath('dateFin')
                    ->addViolation();
            }
        }

        // 2. Validation du pourcentage
        if ($this->typeReduction === 'POURCENTAGE' && $this->valeur > 100) {
            $context->buildViolation('Une réduction en pourcentage ne peut pas dépasser 100%.')
                ->atPath('valeur')
                ->addViolation();
        }
    }
}
