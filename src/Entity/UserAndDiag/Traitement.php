<?php

namespace App\Entity\UserAndDiag;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: \App\Repository\UserAndDiag\TraitementRepository::class)]
class Traitement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Diagnostic::class)]
    #[ORM\JoinColumn(name: 'diagnostic_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Diagnostic $diagnostic = null;

    #[ORM\Column(length: 255)]
    private string $solution_nom;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description_detaillee = null;

    #[ORM\Column(type: Types::STRING, columnDefinition: "ENUM('FONGICIDE','HERBICIDE','INSECTICIDE','BACTERICIDE','NEMATICIDE','VIRUCIDE','NUTRIMENT','REGULATEUR_CROISSANCE','AUTRE') DEFAULT 'AUTRE'", nullable: true)]
    private ?string $type_traitement = 'AUTRE';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $duree_recommandee = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDiagnostic(): ?Diagnostic
    {
        return $this->diagnostic;
    }

    public function setDiagnostic(?Diagnostic $diagnostic): static
    {
        $this->diagnostic = $diagnostic;
        return $this;
    }

    public function getSolutionNom(): ?string
    {
        return $this->solution_nom ?? null;
    }

    public function setSolutionNom(string $solution_nom): static
    {
        $this->solution_nom = $solution_nom;
        return $this;
    }

    public function getDescriptionDetaillee(): ?string
    {
        return $this->description_detaillee;
    }

    public function setDescriptionDetaillee(?string $description_detaillee): static
    {
        $this->description_detaillee = $description_detaillee;
        return $this;
    }

    public function getTypeTraitement(): ?string
    {
        return $this->type_traitement;
    }

    public function setTypeTraitement(?string $type_traitement): static
    {
        $this->type_traitement = $type_traitement;
        return $this;
    }

    public function getDureeRecommandee(): ?string
    {
        return $this->duree_recommandee;
    }

    public function setDureeRecommandee(?string $duree_recommandee): static
    {
        $this->duree_recommandee = $duree_recommandee;
        return $this;
    }
}
