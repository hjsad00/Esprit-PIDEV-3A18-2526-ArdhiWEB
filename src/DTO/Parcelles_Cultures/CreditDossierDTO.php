<?php

namespace App\DTO\Parcelles_Cultures;

use Symfony\Component\Validator\Constraints as Assert;

class CreditDossierDTO
{
    #[Assert\NotBlank(message: 'La durée en années est obligatoire')]
    #[Assert\Range(min: 1, max: 25, notInRangeMessage: 'La durée doit être comprise entre {{ min }} et {{ max }} ans.')]
    public ?int $duree_annees = null;

    #[Assert\NotBlank(message: 'Le score de rentabilité est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le score de rentabilité doit être un nombre.')]
    #[Assert\Range(min: 0, max: 10, notInRangeMessage: 'Le score de rentabilité doit être entre {{ min }} et {{ max }}.')]
    public ?string $score_rentabilite = null;

    #[Assert\NotBlank(message: 'Le score de stabilité climatique est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le score de stabilité climatique doit être un nombre.')]
    #[Assert\Range(min: 0, max: 10, notInRangeMessage: 'Le score de stabilité climatique doit être entre {{ min }} et {{ max }}.')]
    public ?string $score_stabilite_climat = null;

    #[Assert\NotBlank(message: 'Le score de diversification est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le score de diversification doit être un nombre.')]
    #[Assert\Range(min: 0, max: 10, notInRangeMessage: 'Le score de diversification doit être entre {{ min }} et {{ max }}.')]
    public ?string $score_diversification = null;

    #[Assert\NotBlank(message: 'Le score historique est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le score historique doit être un nombre.')]
    #[Assert\Range(min: 0, max: 10, notInRangeMessage: 'Le score historique doit être entre {{ min }} et {{ max }}.')]
    public ?string $score_historique = null;

    #[Assert\NotBlank(message: 'Le prix de vente est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le prix de vente doit être un nombre.')]
    #[Assert\PositiveOrZero(message: 'Le prix de vente doit être >= 0')]
    public ?string $prixVente = null;

    #[Assert\NotBlank(message: 'Le coût des semences est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le coût des semences doit être un nombre.')]
    #[Assert\PositiveOrZero(message: 'Le coût doit être >= 0')]
    public ?string $coutSemences = null;

    #[Assert\NotBlank(message: 'Le coût des engrais est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le coût des engrais doit être un nombre.')]
    #[Assert\PositiveOrZero(message: 'Le coût doit être >= 0')]
    public ?string $coutEngrais = null;

    #[Assert\NotBlank(message: 'Le coût de la main d\'œuvre est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le coût de la main d\'œuvre doit être un nombre.')]
    #[Assert\PositiveOrZero(message: 'Le coût doit être >= 0')]
    public ?string $coutMainOeuvre = null;

    #[Assert\NotBlank(message: 'Le coût d\'irrigation est obligatoire')]
    #[Assert\Type(type: 'numeric', message: 'Le coût d\'irrigation doit être un nombre.')]
    #[Assert\PositiveOrZero(message: 'Le coût doit être >= 0')]
    public ?string $coutIrrigation = null;

    #[Assert\NotBlank(message: 'Les autres coûts sont obligatoires')]
    #[Assert\Type(type: 'numeric', message: 'Les autres coûts doivent être un nombre.')]
    #[Assert\PositiveOrZero(message: 'Le coût doit être >= 0')]
    public ?string $coutAutres = null;
}
