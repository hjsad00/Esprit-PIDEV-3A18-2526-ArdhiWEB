<?php

namespace App\DTO\Parcelles_Cultures;

use Symfony\Component\Validator\Constraints as Assert;

class CultureDTO
{
    #[Assert\NotBlank(message: 'Le nom de la culture est obligatoire')]
    #[Assert\Length(min: 2, max: 255)]
    public ?string $nom_culture = null;

    #[Assert\NotBlank(message: 'Le type de culture est obligatoire')]
    public ?string $type_culture = null;

    #[Assert\NotBlank(message: 'La saison est obligatoire')]
    public ?string $saison = null;

    #[Assert\NotBlank(message: 'La date de plantation est obligatoire')]
    #[Assert\Type(\DateTimeInterface::class)]
    public ?\DateTimeInterface $date_plantation = null;

    #[Assert\NotBlank(message: 'La date de récolte prévue est obligatoire')]
    #[Assert\Type(\DateTimeInterface::class)]
    public ?\DateTimeInterface $date_recolte_prevue = null;

    #[Assert\NotBlank(message: 'La surface utilisée est obligatoire')]
    #[Assert\GreaterThanOrEqual(value: 0)]
    public ?string $surface_utilisee = null;

    #[Assert\NotBlank(message: 'Le rendement estimé est obligatoire')]
    #[Assert\GreaterThan(value: 0)]
    public ?string $rendement_estime = null;
}
