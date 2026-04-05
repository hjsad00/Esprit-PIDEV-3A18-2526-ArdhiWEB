<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\Parcelle;
use App\Entity\Parcelles_Cultures\CreditDossier;
use App\Repository\Parcelles_Cultures\CultureRepository;

/**
 * Service pour l'analyse de crédit agricole
 * Calcule les scores et détermine l'éligibilité au crédit
 */
class CreditAnalysisService
{
    private $culturalRepository;
    private $financialService;

    public function __construct(
        CultureRepository $cultureRepository,
        FinancialService $financialService
    ) {
        $this->cultureRepository = $cultureRepository;
        $this->financialService = $financialService;
    }

    /**
     * Génère un dossier crédit complet pour une parcelle
     */
    public function genererDossier(Parcelle $parcelle, $userId, int $dureeAnnees): CreditDossier
    {
        $dossier = new CreditDossier();
        $dossier->setParcelle($parcelle);
        $dossier->setUser($userId);
        $dossier->setDureeAnnees($dureeAnnees);
        $dossier->setLangue('FR');

        // Calcule les scores
        $scoreRentabilite = $this->calculerScoreRentabilite($parcelle);
        $scoreStabiliteClimat = $this->calculerScoreStabiliteClimat($parcelle);
        $scoreDiversification = $this->calculerScoreDiversification($parcelle);
        $scoreHistorique = $this->calculerScoreHistorique($parcelle);

        $dossier->setScoreRentabilite($scoreRentabilite);
        $dossier->setScoreStabiliteClimat($scoreStabiliteClimat);
        $dossier->setScoreDiversification($scoreDiversification);
        $dossier->setScoreHistorique($scoreHistorique);

        // Calcule le score de risque global
        $scoreRisque = $this->calculerScoreRisque($scoreRentabilite, $scoreStabiliteClimat, $scoreDiversification, $scoreHistorique);
        $dossier->setScoreRisque($scoreRisque);

        // Détermine le niveau de risque
        $niveauRisque = $this->determinerNiveauRisque($scoreRisque);
        $dossier->setNiveauRisque($niveauRisque);

        // Calcule la capacité de remboursement
        $capaciteRemboursement = $this->calculerCapaciteRemboursement($parcelle, $dureeAnnees);
        $dossier->setCapaciteRemboursement($capaciteRemboursement);

        // Calcule le montant maximal du prêt
        $montantPretMax = $this->calculerMontantPretMax($capaciteRemboursement, $niveauRisque);
        $dossier->setMontantPretMax($montantPretMax);

        $dossier->setDateCreation(new \DateTime());

        return $dossier;
    }

    /**
     * Calcule le score de rentabilité (0-100)
     * Base: ROI de la parcelle
     */
    public function calculerScoreRentabilite(Parcelle $parcelle): float
    {
        $cultures = $this->cultureRepository->findByParcelle($parcelle->getId());
        
        if (empty($cultures)) {
            return 0;
        }

        $roiTotal = 0;
        foreach ($cultures as $culture) {
            $roiTotal += $this->financialService->calculerRoi($culture);
        }

        $roiMoyen = $roiTotal / count($cultures);
        // Convertit ROI en score (0-100): ROI 0% = score 0, ROI 100% = score 100
        return min(100, max(0, $roiMoyen));
    }

    /**
     * Calcule le score de stabilité climatique (0-100)
     * Base: type de culture et saison
     */
    public function calculerScoreStabiliteClimat(Parcelle $parcelle): float
    {
        $cultures = $this->cultureRepository->findByParcelle($parcelle->getId());
        
        if (empty($cultures)) {
            return 50;
        }

        $scoreTotal = 0;
        foreach ($cultures as $culture) {
            // Saison sèche = score plus faible
            $score = $culture->getSaison() === 'Saison sèche' ? 50 : 75;
            // Diversification réduit le risque
            $score += 10;
            $scoreTotal += $score;
        }

        $scoreMoyen = $scoreTotal / count($cultures);
        return min(100, max(0, $scoreMoyen));
    }

    /**
     * Calcule le score de diversification (0-100)
     * Plus de variétés = score plus élevé
     */
    public function calculerScoreDiversification(Parcelle $parcelle): float
    {
        $cultures = $this->cultureRepository->findByParcelle($parcelle->getId());
        
        $nbCultures = count($cultures);
        // 1 culture = 40, 3+ cultures = 100
        $score = 40 + ($nbCultures - 1) * 20;
        return min(100, max(40, $score));
    }

    /**
     * Calcule le score d'historique (0-100)
     * Supposition: nouveau client = 60, croissance = 80-90
     */
    public function calculerScoreHistorique(Parcelle $parcelle): float
    {
        // Supposition: toutes les parcelles nouvelles = score neutre
        return 70;
    }

    /**
     * Calcule le score de risque global (pondéré)
     * Pondération: Rentabilité 40%, Stabilité 30%, Diversification 20%, Historique 10%
     */
    public function calculerScoreRisque(
        float $scoreRentabilite,
        float $scoreStabiliteClimat,
        float $scoreDiversification,
        float $scoreHistorique
    ): float {
        $scoreRisque = 100 - (
            ($scoreRentabilite * 0.4) +
            ($scoreStabiliteClimat * 0.3) +
            ($scoreDiversification * 0.2) +
            ($scoreHistorique * 0.1)
        );

        return max(0, min(100, $scoreRisque));
    }

    /**
     * Détermine le niveau de risque selon le score
     */
    public function determinerNiveauRisque(float $scoreRisque): string
    {
        if ($scoreRisque < 20) {
            return 'Faible';
        } elseif ($scoreRisque < 50) {
            return 'Modéré';
        } elseif ($scoreRisque < 75) {
            return 'Élevé';
        } else {
            return 'Très Élevé';
        }
    }

    /**
     * Calcule la capacité de remboursement annuelle
     */
    public function calculerCapaciteRemboursement(Parcelle $parcelle, int $dureeAnnees): float
    {
        $cultures = $this->cultureRepository->findByParcelle($parcelle->getId());
        
        $margeBruteTotal = 0;
        foreach ($cultures as $culture) {
            $margeBruteTotal += $this->financialService->calculerMargeBrute($culture);
        }

        // 50% de la marge brute peut être alloué au remboursement
        return max(0, ($margeBruteTotal * 0.5) / $dureeAnnees);
    }

    /**
     * Calcule le montant maximal du prêt selon le niveau de risque
     */
    public function calculerMontantPretMax(float $capaciteRemboursement, string $niveauRisque): float
    {
        // Ratio LTV (Loan to Value) selon le risque
        $ratiosParRisque = [
            'Faible' => 5,
            'Modéré' => 4,
            'Élevé' => 3,
            'Très Élevé' => 1.5
        ];

        $ratio = $ratiosParRisque[$niveauRisque] ?? 3;
        return $capaciteRemboursement * $ratio * 12; // 12 mois
    }

    /**
     * Analyse le crédit complet
     */
    public function analyserCredit(Parcelle $parcelle, $userId, int $dureeAnnees): array
    {
        $dossier = $this->genererDossier($parcelle, $userId, $dureeAnnees);

        return [
            'dossier' => $dossier,
            'scores' => [
                'rentabilite' => $dossier->getScoreRentabilite(),
                'stabilite_climat' => $dossier->getScoreStabiliteClimat(),
                'diversification' => $dossier->getScoreDiversification(),
                'historique' => $dossier->getScoreHistorique(),
                'risque_global' => $dossier->getScoreRisque()
            ],
            'niveau_risque' => $dossier->getNiveauRisque(),
            'montant_max' => $dossier->getMontantPretMax(),
            'capacite_remboursement' => $dossier->getCapaciteRemboursement()
        ];
    }
}
