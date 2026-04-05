<?php

namespace App\DTO\Parcelles_Cultures;

use Symfony\Component\Validator\Constraints as Assert;

class CultureDTO
{
    #[Assert\NotBlank(message: 'Le nom de la culture est requis')]
    #[Assert\Length(min: 3, max: 255)]
    public ?string $nom_culture = null;

    #[Assert\NotBlank(message: 'Le type de culture est requis')]
    #[Assert\Choice(choices: ['Légume', 'Céréale', 'Fruit', 'Fourrage', 'Légumineuse'])]
    public ?string $type_culture = null;

    #[Assert\NotBlank(message: 'La saison est requise')]
    #[Assert\Choice(choices: ['Courte saison 1', 'Courte saison 2', 'Grande saison', 'Saison sèche'])]
    public ?string $saison = null;

    #[Assert\NotBlank(message: 'La date de plantation est requise')]
    #[Assert\Type(\DateTime::class)]
    public ?\DateTime $date_plantation = null;

    #[Assert\NotBlank(message: 'La date de récolte prévue est requise')]
    #[Assert\Type(\DateTime::class)]
    public ?\DateTime $date_recolte_prevue = null;

    #[Assert\NotBlank(message: 'L\'état de la culture est requis')]
    #[Assert\Choice(choices: ['Plantée', 'En croissance', 'Mature', 'Récoltée'])]
    public ?string $etat_culture = null;

    #[Assert\NotBlank(message: 'La surface utilisée est requise')]
    #[Assert\Positive(message: 'La surface utilisée doit être positive')]
    public ?float $surface_utilisee = null;

    #[Assert\NotBlank(message: 'Le rendement estimé est requis')]
    #[Assert\Positive(message: 'Le rendement estimé doit être positif')]
    public ?float $rendement_estime = null;

    public int $parcelle_id;

    public function __construct(
        ?string $nom_culture = null,
        ?string $type_culture = null,
        ?string $saison = null,
        ?\DateTime $date_plantation = null,
        ?\DateTime $date_recolte_prevue = null,
        ?string $etat_culture = null,
        ?float $surface_utilisee = null,
        ?float $rendement_estime = null,
        int $parcelle_id = 0
    ) {
        $this->nom_culture = $nom_culture;
        $this->type_culture = $type_culture;
        $this->saison = $saison;
        $this->date_plantation = $date_plantation;
        $this->date_recolte_prevue = $date_recolte_prevue;
        $this->etat_culture = $etat_culture;
        $this->surface_utilisee = $surface_utilisee;
        $this->rendement_estime = $rendement_estime;
        $this->parcelle_id = $parcelle_id;
    }
}
