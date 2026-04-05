<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;
use App\Repository\Parcelles_Cultures\CultureRepository;

class CultureService
{
    private $cultureRepository;

    public function __construct(CultureRepository $cultureRepository)
    {
        $this->cultureRepository = $cultureRepository;
    }

    /**
     * Vérifie que date_plantation < date_recolte_prevue
     */
    public function isValidDates(\DateTime $plantDate, \DateTime $harvestDate): bool
    {
        return $plantDate < $harvestDate;
    }

    /**
     * Vérifie que somme(surface_utilisee) <= surface de la parcelle
     */
    public function verifierContrainteSurface(Culture $culture): bool
    {
        $totalSurface = $this->cultureRepository->getSurfaceUtiliseeTotalByParcelle(
            $culture->getParcelle()->getId()
        );

        if ($culture->getId()) {
            $totalSurface -= $culture->getSurfaceUtilisee();
        }

        return ($totalSurface + $culture->getSurfaceUtilisee()) <= $culture->getParcelle()->getSurface();
    }

    /**
     * Récupère la surface utilisée totale par parcelle
     */
    public function getSurfaceUtiliseeParParcelle($parcelleId): float
    {
        return $this->cultureRepository->getSurfaceUtiliseeTotalByParcelle($parcelleId);
    }

    /**
     * Récupère les cultures prêtes à récolter
     */
    public function getCulturesPretesARecolter($agriculteurId)
    {
        return $this->cultureRepository->getCulturesPretesARecolter($agriculteurId);
    }

    /**
     * Calcule la production estimée (surface * rendement)
     */
    public function calculerProductionEstimee(Culture $culture): float
    {
        return $culture->getSurfaceUtilisee() * $culture->getRendementEstime();
    }

    /**
     * Récupère le nombre de jours de végétation selon le type et la saison
     */
    public function getJoursVegetation(Culture $culture): int
    {
        $joursParType = [
            'Légume' => 90,
            'Céréale' => 120,
            'Fruit' => 180,
            'Fourrage' => 60,
            'Légumineuse' => 110
        ];

        return $joursParType[$culture->getTypeCulture()] ?? 100;
    }
}
