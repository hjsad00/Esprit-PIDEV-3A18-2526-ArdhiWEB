<?php

namespace App\DTO\Parcelles_Cultures;

use Symfony\Component\Validator\Constraints as Assert;

class RoiDTO
{
    #[Assert\NotBlank(message: 'La surface est obligatoire')]
    #[Assert\GreaterThan(value: 0)]
    public ?float $surface_ha = null;

    #[Assert\NotBlank(message: 'Le rendement est obligatoire')]
    #[Assert\GreaterThan(value: 0)]
    public ?float $rendement = null;

    #[Assert\NotBlank(message: 'Le prix de vente est obligatoire')]
    #[Assert\GreaterThan(value: 0)]
    public ?float $prix_vente = null;

    #[Assert\NotNull]
    public ?int $jours_canicule = 0;

    #[Assert\NotNull]
    public ?int $jours_excespluie = 0;

    #[Assert\NotNull]
    public ?int $jours_gel = 0;

    #[Assert\NotBlank(message: 'Le coût des semences est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0)]
    public ?float $cout_semences = null;

    #[Assert\NotBlank(message: 'Le coût des engrais est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0)]
    public ?float $cout_engrais = null;

    #[Assert\NotBlank(message: 'Le coût de la main d\'œuvre est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0)]
    public ?float $cout_main_oeuvre = null;

    #[Assert\NotBlank(message: 'Le coût de l\'irrigation est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0)]
    public ?float $cout_irrigation = null;

    #[Assert\NotBlank(message: 'Les autres coûts sont obligatoires')]
    #[Assert\GreaterThanOrEqual(value: 0)]
    public ?float $cout_autres = null;
}
