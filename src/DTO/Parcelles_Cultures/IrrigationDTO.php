<?php

namespace App\DTO\Parcelles_Cultures;

use Symfony\Component\Validator\Constraints as Assert;

class IrrigationDTO
{
    #[Assert\NotBlank(message: 'La date est obligatoire')]
    #[Assert\Type(\DateTimeInterface::class)]
    public ?\DateTimeInterface $date = null;

    #[Assert\NotBlank(message: 'La température min est obligatoire')]
    #[Assert\Type('numeric')]
    public ?string $temperature_min = null;

    #[Assert\NotBlank(message: 'La température max est obligatoire')]
    #[Assert\Type('numeric')]
    public ?string $temperature_max = null;

    public ?string $temperature_moyenne = null;

    #[Assert\Callback]
    public function validateTemperatures(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->temperature_min !== null && $this->temperature_max !== null && $this->temperature_min >= $this->temperature_max) {
            $context->buildViolation('La température minimale doit être inférieure à la température maximale.')
                ->atPath('temperature_min')
                ->addViolation();
        }
    }

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
