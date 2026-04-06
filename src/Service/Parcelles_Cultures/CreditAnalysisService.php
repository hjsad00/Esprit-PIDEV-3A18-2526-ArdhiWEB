<?php

namespace App\Service\Parcelles_Cultures;

use App\Entity\Parcelles_Cultures\CreditDossier;
use App\Entity\Parcelles_Cultures\Parcelle;
use App\Entity\UserAndDiag\User;

class CreditAnalysisService
{
    protected FinancialService $financialService;

    public function __construct(FinancialService $financialService)
    {
        $this->financialService = $financialService;
    }

    /**
     * CapaciteRemboursement = MargeBrute × 0.60
     */
    public function calculerCapaciteRemboursement(float $margeBrute): float
    {
        return $margeBrute * 0.60;
    }

    /**
     * MontantPretMax = CapaciteRemboursement × DureeAnnees
     */
    public function calculerMontantPretMax(float $capaciteRemboursement, int $dureeAnnees): float
    {
        return $capaciteRemboursement * $dureeAnnees;
    }

    /**
     * ScoreRisque = 0.4×Rentabilite + 0.3×StabiliteClimat + 0.2×Diversification + 0.1×Historique
     * NiveauRisque: Faible si >= 7, Modéré si >= 4, sinon Élevé
     */
    public function calculerScoreRisque(
        float $scoreRentabilite,
        float $scoreStabiliteClimat,
        float $scoreDiversification,
        float $scoreHistorique
    ): array {
        $scoreRisque = (0.4 * $scoreRentabilite)
            + (0.3 * $scoreStabiliteClimat)
            + (0.2 * $scoreDiversification)
            + (0.1 * $scoreHistorique);

        if ($scoreRisque >= 7) {
            $niveauRisque = 'faible';
        } elseif ($scoreRisque >= 4) {
            $niveauRisque = 'modere';
        } else {
            $niveauRisque = 'eleve';
        }

        return [
            'score_risque' => round($scoreRisque, 2),
            'niveau_risque' => $niveauRisque,
        ];
    }

    /**
     * Génère un dossier de crédit complet
     */
    public function genererDossier(
        Parcelle $parcelle,
        User $user,
        int $dureeAnnees,
        float $scoreRentabilite,
        float $scoreStabiliteClimat,
        float $scoreDiversification,
        float $scoreHistorique,
        float $margeBrute
    ): CreditDossier {
        $riskData = $this->calculerScoreRisque(
            $scoreRentabilite,
            $scoreStabiliteClimat,
            $scoreDiversification,
            $scoreHistorique
        );

        $capaciteRemboursement = $this->calculerCapaciteRemboursement($margeBrute);
        $montantPretMax = $this->calculerMontantPretMax($capaciteRemboursement, $dureeAnnees);

        $dossier = new CreditDossier();
        $dossier->setParcelle($parcelle);
        $dossier->setDureeAnnees($dureeAnnees);
        $dossier->setScoreRentabilite((string) $scoreRentabilite);
        $dossier->setScoreStabiliteClimat((string) $scoreStabiliteClimat);
        $dossier->setScoreDiversification((string) $scoreDiversification);
        $dossier->setScoreHistorique((string) $scoreHistorique);
        $dossier->setCapaciteRemboursement((string) $capaciteRemboursement);
        $dossier->setMontantPretMax((string) $montantPretMax);
        $dossier->setScoreRisque((string) $riskData['score_risque']);
        $dossier->setNiveauRisque($riskData['niveau_risque']);

        $recommendations = $this->genererRecommandations($riskData['niveau_risque'], $scoreRentabilite);
        $dossier->setRecommandations($recommendations);

        return $dossier;
    }

    /**
     * Génère des recommandations en fonction du risque et de la rentabilité
     */
    private function genererRecommandations(string $niveauRisque, float $scoreRentabilite): string
    {
        $recommandations = [];

        if ($niveauRisque === 'eleve') {
            $recommandations[] = "⚠️ RISQUE ÉLEVÉ: Il est recommandé de réduire le montant du crédit demandé.";
        } elseif ($niveauRisque === 'modere') {
            $recommandations[] = "⚠️ RISQUE MODÉRÉ: À surveiller étroitement.";
        } else {
            $recommandations[] = "✅ RISQUE FAIBLE: Bon candidat pour le crédit.";
        }

        if ($scoreRentabilite < 10) {
            $recommandations[] = "📊 Améliorer la rentabilité : considérez l'optimisation des coûts de production.";
        }

        return implode("\n", $recommandations);
    }
}
