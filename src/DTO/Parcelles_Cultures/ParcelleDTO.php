<?php

namespace App\DTO\Parcelles_Cultures;

use Symfony\Component\Validator\Constraints as Assert;

class ParcelleDTO
{
    #[Assert\NotBlank(message: 'La surface est requise')]
    #[Assert\Positive(message: 'La surface doit être positive')]
    public ?float $surface = null;

    #[Assert\NotBlank(message: 'La localisation est requise')]
    #[Assert\Length(min: 3, max: 255)]
    public ?string $localisation = null;

    #[Assert\NotBlank(message: 'Le type de sol est requis')]
    #[Assert\Choice(choices: ['Argile', 'Sable', 'Limon', 'Tourbe', 'Calcaire'])]
    public ?string $type_sol = null;

    #[Assert\NotBlank(message: 'Le système d\'irrigation est requis')]
    #[Assert\Choice(choices: ['Goutte à goutte', 'Aspersion', 'Rainage', 'Manuel'])]
    public ?string $systeme_irrigation = null;

    #[Assert\NotBlank(message: 'Le statut est requis')]
    #[Assert\Choice(choices: ['Active', 'Inactive', 'En repos'])]
    public ?string $statut = null;

    #[Assert\NotBlank(message: 'La latitude est requise')]
    #[Assert\Range(min: -90, max: 90)]
    public ?float $latitude = null;

    #[Assert\NotBlank(message: 'La longitude est requise')]
    #[Assert\Range(min: -180, max: 180)]
    public ?float $longitude = null;

    public int $agriculteur_id;

    public function __construct(
        ?float $surface = null,
        ?string $localisation = null,
        ?string $type_sol = null,
        ?string $systeme_irrigation = null,
        ?string $statut = null,
        ?float $latitude = null,
        ?float $longitude = null,
        int $agriculteur_id = 0
    ) {
        $this->surface = $surface;
        $this->localisation = $localisation;
        $this->type_sol = $type_sol;
        $this->systeme_irrigation = $systeme_irrigation;
        $this->statut = $statut;
        $this->latitude = $latitude;
        $this->longitude = $longitude;
        $this->agriculteur_id = $agriculteur_id;
    }
}
