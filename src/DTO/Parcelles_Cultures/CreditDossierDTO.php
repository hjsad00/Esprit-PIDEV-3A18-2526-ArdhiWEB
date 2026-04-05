<?php

namespace App\DTO\Parcelles_Cultures;

use Symfony\Component\Validator\Constraints as Assert;

class CreditDossierDTO
{
    #[Assert\NotBlank(message: 'La parcelle est requise')]
    public ?int $parcelle_id = null;

    #[Assert\NotBlank(message: 'L\'utilisateur est requis')]
    public ?int $user_id = null;

    #[Assert\NotBlank(message: 'La durée du crédit est requise')]
    #[Assert\Positive(message: 'La durée doit être positive')]
    public ?int $duree_annees = null;

    #[Assert\Choice(choices: ['FR', 'EN'])]
    public string $langue = 'FR';

    public ?float $score_risque = null;
    public ?string $niveau_risque = null;
    public ?float $montant_pret_max = null;
    public ?float $capacite_remboursement = null;

    public ?float $score_rentabilite = null;
    public ?float $score_stabilite_climat = null;
    public ?float $score_diversification = null;
    public ?float $score_historique = null;

    public function __construct(
        ?int $parcelle_id = null,
        ?int $user_id = null,
        ?int $duree_annees = null,
        string $langue = 'FR'
    ) {
        $this->parcelle_id = $parcelle_id;
        $this->user_id = $user_id;
        $this->duree_annees = $duree_annees;
        $this->langue = $langue;
    }
}
