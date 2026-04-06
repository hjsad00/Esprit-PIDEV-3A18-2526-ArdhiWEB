<?php

namespace App\Entity\Marketplace;

use App\Repository\Marketplace\CouponRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CouponRepository::class)]
#[ORM\Table(name: 'coupon')]
class Coupon
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'idCoupon')]
    private ?int $id = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(name: 'typeReduction', type: Types::STRING, length: 20, columnDefinition: "ENUM('POURCENTAGE', 'FIXE') NOT NULL")]
    private ?string $typeReduction = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $valeur = null;

    #[ORM\Column(name: 'dateDebut', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'dateFin', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(name: 'utilisationMax', options: ['default' => 0])]
    private ?int $utilisationMax = 0;

    #[ORM\Column(name: 'utilisationActuelle', options: ['default' => 0])]
    private ?int $utilisationActuelle = 0;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $actif = true;

    #[ORM\Column(name: 'montantMin', type: Types::FLOAT, options: ['default' => 0])]
    private float $montantMin = 0;

    #[ORM\Column(name: 'limiteParUser', options: ['default' => 1])]
    private ?int $limiteParUser = 1;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;
        return $this;
    }

    public function getTypeReduction(): ?string
    {
        return $this->typeReduction;
    }

    public function setTypeReduction(string $typeReduction): static
    {
        $this->typeReduction = $typeReduction;
        return $this;
    }

    public function getValeur(): ?float
    {
        return $this->valeur;
    }

    public function setValeur(float $valeur): static
    {
        $this->valeur = $valeur;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getUtilisationMax(): ?int
    {
        return $this->utilisationMax;
    }

    public function setUtilisationMax(int $utilisationMax): static
    {
        $this->utilisationMax = $utilisationMax;
        return $this;
    }

    public function getUtilisationActuelle(): ?int
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

    public function getLimiteParUser(): ?int
    {
        return $this->limiteParUser;
    }

    public function setLimiteParUser(int $limiteParUser): static
    {
        $this->limiteParUser = $limiteParUser;
        return $this;
    }
}
