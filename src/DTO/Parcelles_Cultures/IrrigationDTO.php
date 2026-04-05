<?php

namespace App\DTO\Parcelles_Cultures;

use Symfony\Component\Validator\Constraints as Assert;

class IrrigationDTO
{
    #[Assert\NotBlank(message: 'La date est obligatoire')]
    #[Assert\Type(\DateTimeInterface::class)]
    public ?\DateTimeInterface $date = null;

    #[Assert\NotBlank]
    #[Assert\Type('numeric')]
    public ?string $temperature_moyenne = null;

    #[Assert\NotBlank]
    #[Assert\Type('numeric')]
    public ?string $temperature_max = null;

    #[Assert\NotBlank]
    #[Assert\Type('numeric')]
    public ?string $temperature_min = null;

    #[Assert\NotBlank]
    #[Assert\GreaterThanOrEqual(0)]
    public ?string $precipitations = null;

    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 100)]
    public ?string $humidite = null;

    #[Assert\NotBlank]
    #[Assert\GreaterThan(0)]
    public ?string $kc = null;
}
