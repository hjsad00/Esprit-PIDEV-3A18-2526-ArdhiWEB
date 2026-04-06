<?php

namespace App\DTO\Parcelles_Cultures;

use Symfony\Component\Validator\Constraints as Assert;

class CultureDTO
{
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

    #[Assert\NotBlank(message: 'La parcelle est obligatoire')]
    public ?\App\Entity\Parcelles_Cultures\Parcelle $parcelle = null;

    #[Assert\Callback]
    public function validateDates(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        if ($this->date_plantation && $this->date_recolte_prevue && $this->date_plantation >= $this->date_recolte_prevue) {
            $context->buildViolation('La date de plantation doit être antérieure à la date de récolte.')
                ->atPath('date_plantation')
                ->addViolation();
        }
    }
}
