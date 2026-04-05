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

    #[Assert\NotBlank(message: 'Le prix de vente est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le prix de vente doit être >= 0')]
    public ?string $prixVente = null;

    #[Assert\NotBlank(message: 'Le coût des semences est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le coût doit être >= 0')]
    public ?string $coutSemences = null;

    #[Assert\NotBlank(message: 'Le coût des engrais est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le coût doit être >= 0')]
    public ?string $coutEngrais = null;

    #[Assert\NotBlank(message: 'Le coût de la main d\'œuvre est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le coût doit être >= 0')]
    public ?string $coutMainOeuvre = null;

    #[Assert\NotBlank(message: 'Le coût d\'irrigation est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le coût doit être >= 0')]
    public ?string $coutIrrigation = null;

    #[Assert\NotBlank(message: 'Les autres coûts sont obligatoires')]
    #[Assert\PositiveOrZero(message: 'Le coût doit être >= 0')]
    public ?string $coutAutres = null;
}
