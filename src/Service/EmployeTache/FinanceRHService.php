<?php

namespace App\Service\EmployeTache;

use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;

/**
 * 💰 FinanceRHService
 *
 * Calcule les coûts RH, les cotisations CNSS tunisiennes,
 * le coût de revient des tâches et les indicateurs financiers globaux.
 *
 * TAUX CNSS TUNISIE 2024 (Décret n°2024-xxx) :
 *  - Employé  :  9.18% du salaire brut
 *  - Employeur: 16.57% du salaire brut
 *  - IRPP      : calculé par tranches (simplifié)
 */
class FinanceRHService
{
    // ── Taux CNSS officiels Tunisie 2024 ─────────────────────────────
    public const CNSS_EMPLOYE    = 0.0918;   // 9.18%
    public const CNSS_EMPLOYEUR  = 0.1657;   // 16.57%

    // ── Salaires journaliers de référence par poste (TND) ────────────
    private const SALAIRES_REFERENCE = [
        'Ingénieur'         => 95.0,
        'ing'               => 95.0,
        'Agronome'          => 80.0,
        'Superviseure'      => 70.0,
        'Technicien'        => 55.0,
        'Technicien maint.' => 55.0,
        'Ouvrier agricole'  => 42.0,
        'Ouvrière'          => 42.0,
        'Resp. récolte'     => 45.0,
    ];

    public function __construct(
        private EmployeRepository $employeRepo,
        private TacheRepository   $tacheRepo,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    //  DASHBOARD FINANCIER GLOBAL
    // ══════════════════════════════════════════════════════════════════

    public function getDashboardFinancier(int $idAgriculteur): array
    {
        $employes = $this->employeRepo->findActifsByAgriculteur($idAgriculteur);
        $taches   = $this->tacheRepo->findByAgriculteur($idAgriculteur);

        $masseSalarialeMensuelle = 0.0;
        $coutCNSSEmployeur       = 0.0;
        $employesData            = [];

        foreach ($employes as $emp) {
            $salaireJ  = $this->getSalaireJournalier($emp);
            $salMensuel = $salaireJ * 26; // 26 jours ouvrables/mois

            $cnssEmp    = $salMensuel * self::CNSS_EMPLOYE;
            $cnssEmpr   = $salMensuel * self::CNSS_EMPLOYEUR;
            $irpp       = $this->calculerIRPP($salMensuel);
            $salaireNet = $salMensuel - $cnssEmp - $irpp;
            $coutTotal  = $salMensuel + $cnssEmpr; // Coût employeur réel

            $masseSalarialeMensuelle += $salMensuel;
            $coutCNSSEmployeur       += $cnssEmpr;

            // Tâches de cet employé
            $tachesEmp = array_filter($taches, fn($t) => $t->getIdEmploye() === $emp->getId());
            $nbTaches  = count($tachesEmp);

            $employesData[] = [
                'employe'       => $emp,
                'salaireJ'      => round($salaireJ, 3),
                'salaireBrut'   => round($salMensuel, 3),
                'cnssEmploye'   => round($cnssEmp, 3),
                'cnssEmployeur' => round($cnssEmpr, 3),
                'irpp'          => round($irpp, 3),
                'salaireNet'    => round($salaireNet, 3),
                'coutTotal'     => round($coutTotal, 3),
                'nbTaches'      => $nbTaches,
                'typeContrat'   => $this->getTypeContrat($emp),
            ];
        }

        // ── KPIs tâches ──────────────────────────────────────────────
        $budgetTotal    = 0.0;
        $coutReel       = 0.0;
        $coutMateriel   = 0.0;
        $tachesData     = [];

        foreach ($taches as $tache) {
            $analyse = $this->analyserCoutTache($tache, $employes);
            $tachesData[] = $analyse;
            $budgetTotal  += $analyse['budgetPrevu'];
            $coutReel     += $analyse['coutReel'];
            $coutMateriel += $analyse['coutMateriel'];
        }

        // ── Répartition par catégorie ─────────────────────────────────
        $parCategorie = [];
        foreach ($tachesData as $td) {
            $cat = $td['categorie'] ?? 'Autre';
            if (!isset($parCategorie[$cat])) {
                $parCategorie[$cat] = ['budget' => 0, 'cout' => 0, 'nb' => 0];
            }
            $parCategorie[$cat]['budget'] += $td['budgetPrevu'];
            $parCategorie[$cat]['cout']   += $td['coutReel'];
            $parCategorie[$cat]['nb']++;
        }

        // ── Projection annuelle ───────────────────────────────────────
        $chargesAnnuelles = ($masseSalarialeMensuelle + $coutCNSSEmployeur) * 12;

        return [
            'employes'               => $employesData,
            'taches'                 => $tachesData,
            'parCategorie'           => $parCategorie,
            'masseSalarialeMensuelle'=> round($masseSalarialeMensuelle, 3),
            'coutCNSSEmployeur'      => round($coutCNSSEmployeur, 3),
            'coutTotalMensuel'       => round($masseSalarialeMensuelle + $coutCNSSEmployeur, 3),
            'chargesAnnuelles'       => round($chargesAnnuelles, 3),
            'budgetTaches'           => round($budgetTotal, 3),
            'coutReelTaches'         => round($coutReel, 3),
            'coutMateriel'           => round($coutMateriel, 3),
            'ecartBudget'            => round($coutReel - $budgetTotal, 3),
            'tauxCNSSEmploye'        => self::CNSS_EMPLOYE * 100,
            'tauxCNSSEmployeur'      => self::CNSS_EMPLOYEUR * 100,
            'nbEmployesActifs'       => count($employes),
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  BULLETIN DE PAIE D'UN EMPLOYÉ
    // ══════════════════════════════════════════════════════════════════

    public function genererBulletinPaie(int $idEmploye, int $mois, int $annee): array
    {
        $emp       = $this->employeRepo->find($idEmploye);
        if (!$emp) return [];

        $nbJoursTravailles = $this->calculerJoursTravailles($mois, $annee);
        $salaireJ          = $this->getSalaireJournalier($emp);
        $salaireBrut       = $salaireJ * $nbJoursTravailles;

        $cnssEmp    = $salaireBrut * self::CNSS_EMPLOYE;
        $cnssEmpr   = $salaireBrut * self::CNSS_EMPLOYEUR;
        $irpp       = $this->calculerIRPP($salaireBrut);
        $salaireNet = $salaireBrut - $cnssEmp - $irpp;

        $moisLabel = $this->getMoisLabel($mois);

        return [
            'employe'          => $emp,
            'mois'             => $moisLabel,
            'annee'            => $annee,
            'nbJoursTravailles'=> $nbJoursTravailles,
            'salaireJournalier'=> round($salaireJ, 3),
            'salaireBrut'      => round($salaireBrut, 3),
            'cnssEmploye'      => round($cnssEmp, 3),
            'cnssEmployeur'    => round($cnssEmpr, 3),
            'irpp'             => round($irpp, 3),
            'salaireNet'       => round($salaireNet, 3),
            'coutTotalEmpl'    => round($salaireBrut + $cnssEmpr, 3),
            'tauxCNSSEmp'      => self::CNSS_EMPLOYE * 100,
            'tauxCNSSEmpr'     => self::CNSS_EMPLOYEUR * 100,
            'typeContrat'      => $this->getTypeContrat($emp),
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  COÛT DE REVIENT D'UNE TÂCHE
    // ══════════════════════════════════════════════════════════════════

    public function analyserCoutTache(object $tache, array $employes): array
    {
        $nbJours = 0;
        if ($tache->getDateDebut() && $tache->getDateFin()) {
            $nbJours = max(1, $tache->getDateDebut()->diff($tache->getDateFin())->days);
        }

        $salaireJ    = 0.0;
        $nomEmploye  = '—';
        if ($tache->getIdEmploye()) {
            foreach ($employes as $emp) {
                if ($emp->getId() === $tache->getIdEmploye()) {
                    $salaireJ   = $this->getSalaireJournalier($emp);
                    $nomEmploye = $emp->getNomComplet();
                    break;
                }
            }
        }

        $coutSalaire  = $salaireJ * $nbJours;
        $coutMat      = $this->getCoutMateriel($tache);
        $budgetPrevu  = $this->getBudgetPrevu($tache);
        $coutReel     = $coutSalaire + $coutMat;
        $ecart        = $coutReel - $budgetPrevu;
        $pctEcart     = $budgetPrevu > 0 ? ($ecart / $budgetPrevu) * 100 : 0;

        return [
            'tache'       => $tache,
            'categorie'   => $tache->getCategorie() ?? 'Autre',
            'nomEmploye'  => $nomEmploye,
            'nbJours'     => $nbJours,
            'salaireJ'    => round($salaireJ, 3),
            'coutSalaire' => round($coutSalaire, 3),
            'coutMateriel'=> round($coutMat, 3),
            'budgetPrevu' => round($budgetPrevu, 3),
            'coutReel'    => round($coutReel, 3),
            'ecart'       => round($ecart, 3),
            'pctEcart'    => round($pctEcart, 1),
            'statut'      => $tache->getStatut(),
            'enSurbudget' => $ecart > 0,
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════════

    private function getSalaireJournalier(object $emp): float
    {
        // Si colonne salaire_journalier existe et est renseignée
        if (method_exists($emp, 'getSalaireJournalier') && $emp->getSalaireJournalier() > 0) {
            return (float) $emp->getSalaireJournalier();
        }
        // Sinon : référence par poste
        $poste = $emp->getPoste() ?? '';
        return self::SALAIRES_REFERENCE[$poste] ?? 40.0;
    }

    private function getTypeContrat(object $emp): string
    {
        if (method_exists($emp, 'getTypeContrat') && $emp->getTypeContrat()) {
            return $emp->getTypeContrat();
        }
        $poste = $emp->getPoste() ?? '';
        return in_array($poste, ['Ouvrier agricole', 'Ouvrière', 'Resp. récolte'])
            ? 'Saisonnier' : 'CDI';
    }

    private function getBudgetPrevu(object $tache): float
    {
        if (method_exists($tache, 'getBudgetPrevu') && $tache->getBudgetPrevu() !== null) {
            return (float) $tache->getBudgetPrevu();
        }
        return 0.0;
    }

    private function getCoutMateriel(object $tache): float
    {
        if (method_exists($tache, 'getCoutMateriel') && $tache->getCoutMateriel() !== null) {
            return (float) $tache->getCoutMateriel();
        }
        return 0.0;
    }

    /**
     * Calcul IRPP Tunisie — barème 2024 (simplifié mensuel)
     */
    private function calculerIRPP(float $salaireBrutMensuel): float
    {
        $annuel = $salaireBrutMensuel * 12;

        $irppAnnuel = match (true) {
            $annuel <= 5000  => 0.0,
            $annuel <= 10000 => ($annuel - 5000) * 0.26,
            $annuel <= 20000 => 1300 + ($annuel - 10000) * 0.28,
            $annuel <= 30000 => 4100 + ($annuel - 20000) * 0.32,
            $annuel <= 50000 => 7300 + ($annuel - 30000) * 0.35,
            default          => 14300 + ($annuel - 50000) * 0.38,
        };

        return $irppAnnuel / 12;
    }

    private function calculerJoursTravailles(int $mois, int $annee): int
    {
        // Jours ouvrables dans le mois (hors week-ends)
        $total = 0;
        $jours = cal_days_in_month(CAL_GREGORIAN, $mois, $annee);
        for ($j = 1; $j <= $jours; $j++) {
            $dow = (int) date('N', mktime(0, 0, 0, $mois, $j, $annee));
            if ($dow < 6) $total++; // Lun–Ven
        }
        return $total;
    }

    private function getMoisLabel(int $mois): string
    {
        return ['', 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
                'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'][$mois];
    }
}