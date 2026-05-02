<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Culture;
use App\Entity\UserAndDiag\User;
use App\Repository\Parcelles_Cultures\CultureRepository;

class CultureService
{
    public function __construct(
        private CultureRepository $cultureRepository,
        private \App\Repository\Parcelles_Cultures\ParcelleRepository $parcelleRepository
    ) {
    }

    /**
     * Valide que date_plantation < date_recolte_prevue
     */
    public function isValidDates(Culture $culture): bool
    {
        return $culture->getDatePlantation() < $culture->getDateRecoltePrevue();
    }

    /**
     * Vérifie que la surface utilisée est compatible avec la parcelle
     * Règle: somme(surface_utilisee) par parcelle <= parcelle.surface
     */
    public function verifierContrainteSurface(int $parcelleId, float $nouvelleSurface, ?int $excludeCultureId = null): bool
    {
        $totalSurfaceOthers = $this->cultureRepository->getSurfaceUtiliseeParParcelle($parcelleId, $excludeCultureId);
        $parcelle = $this->parcelleRepository->find($parcelleId);

        if (!$parcelle) {
            return false;
        }

        $parcelleMaxSurface = (float) $parcelle->getSurface();
        return ($totalSurfaceOthers + $nouvelleSurface) <= $parcelleMaxSurface;
    }

    /**
     * Obtient la surface utilisée totale pour une parcelle
     */
    public function getSurfaceUtiliseeParParcelle(int $parcelleId, ?int $excludeCultureId = null): float
    {
        return $this->cultureRepository->getSurfaceUtiliseeParParcelle($parcelleId, $excludeCultureId);
    }

    /**
     * Récupère les cultures prêtes à récolter pour un agriculteur
     *
     * @return Culture[]
     */
    public function getCulturesPretesARecolter(User $agriculteur): array
    {
        return $this->cultureRepository->getCulturesPretesARecolter($agriculteur);
    }

    /**
     * Calcule la production estimée
     * Formule: ProductionEstimee = SurfaceUtilisee × RendementEstime
     */
    public function calculerProductionEstimee(Culture $culture): float
    {
        $surface = (float) $culture->getSurfaceUtilisee();
        $rendement = (float) $culture->getRendementEstime();

        return $surface * $rendement;
    }

    /**
     * Met à jour la production estimée d'une culture
     */
    public function mettreAJourProductionEstimee(Culture $culture): Culture
    {
        $production = $this->calculerProductionEstimee($culture);
        $culture->setProductionEstimee((string) $production);
        return $culture;
    }
}
