<?php

namespace App\DTO\Parcelles_Cultures;

use Symfony\Component\Validator\Constraints as Assert;

class CreditDossierDTO
{
    #[Assert\NotBlank(message: 'La durée en années est obligatoire')]
    #[Assert\GreaterThan(0)]
    public ?int $duree_annees = null;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(0)]
    public ?string $score_rentabilite = null;

    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 10)]
    public ?string $score_stabilite_climat = null;

    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 10)]
    public ?string $score_diversification = null;

    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 10)]
    public ?string $score_historique = null;

    public ?string $prixVente = null;
    public ?string $coutSemences = null;
    public ?string $coutEngrais = null;
    public ?string $coutMainOeuvre = null;
    public ?string $coutIrrigation = null;
    public ?string $coutAutres = null;
}
