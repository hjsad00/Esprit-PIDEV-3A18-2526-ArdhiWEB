<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\OffreRepository::class)]
class Offre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private ?float $prix_mensuel = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $avantages = null;

    #[ORM\Column(length: 20, nullable: true, options: ["default" => '#6B7F3F'])]
    private ?string $couleur_primaire = '#6B7F3F';

    #[ORM\Column(length: 20, nullable: true, options: ["default" => '#4A5A2B'])]
    private ?string $couleur_secondaire = '#4A5A2B';

    #[ORM\Column(nullable: true, options: ["default" => true])]
    private ?bool $est_active = true;

    #[ORM\Column(nullable: true, options: ["default" => false])]
    private ?bool $est_recommandee = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $date_creation = null;

    #[ORM\Column(nullable: true, options: ["default" => 3])]
    private ?int $diagnostics_par_heure = 3;

    #[ORM\Column(nullable: true, options: ["default" => false])]
    private ?bool $acces_traitement = false;

    #[ORM\Column(nullable: true, options: ["default" => false])]
    private ?bool $acces_plan_traitement = false;

    public function __construct()
    {
        $this->date_creation = new \DateTime();
    }

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getPrixMensuel(): ?float
    {
        return $this->prix_mensuel;
    }

    public function setPrixMensuel(float $prix_mensuel): static
    {
        $this->prix_mensuel = $prix_mensuel;
        return $this;
    }

    public function getAvantages(): ?string
    {
        return $this->avantages;
    }

    public function setAvantages(?string $avantages): static
    {
        $this->avantages = $avantages;
        return $this;
    }

    public function getCouleurPrimaire(): ?string
    {
        return $this->couleur_primaire;
    }

    public function setCouleurPrimaire(?string $couleur_primaire): static
    {
        $this->couleur_primaire = $couleur_primaire;
        return $this;
    }

    public function getCouleurSecondaire(): ?string
    {
        return $this->couleur_secondaire;
    }

    public function setCouleurSecondaire(?string $couleur_secondaire): static
    {
        $this->couleur_secondaire = $couleur_secondaire;
        return $this;
    }

    public function isEstActive(): ?bool
    {
        return $this->est_active;
    }

    public function setEstActive(?bool $est_active): static
    {
        $this->est_active = $est_active;
        return $this;
    }

    public function isEstRecommandee(): ?bool
    {
        return $this->est_recommandee;
    }

    public function setEstRecommandee(?bool $est_recommandee): static
    {
        $this->est_recommandee = $est_recommandee;
        return $this;
    }

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->date_creation;
    }

    public function setDateCreation(?\DateTimeInterface $date_creation): static
    {
        $this->date_creation = $date_creation;
        return $this;
    }

    public function getDiagnosticsParHeure(): ?int
    {
        return $this->diagnostics_par_heure;
    }

    public function setDiagnosticsParHeure(?int $diagnostics_par_heure): static
    {
        $this->diagnostics_par_heure = $diagnostics_par_heure;
        return $this;
    }

    public function isAccesTraitement(): ?bool
    {
        return $this->acces_traitement;
    }

    public function setAccesTraitement(?bool $acces_traitement): static
    {
        $this->acces_traitement = $acces_traitement;
        return $this;
    }

    public function isAccesPlanTraitement(): ?bool
    {
        return $this->acces_plan_traitement;
    }

    public function setAccesPlanTraitement(?bool $acces_plan_traitement): static
    {
        $this->acces_plan_traitement = $acces_plan_traitement;
        return $this;
    }
}
