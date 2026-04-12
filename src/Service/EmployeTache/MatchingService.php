<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Employe;
use App\Entity\EmployeTache\Tache;
use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

class RecommandationResult
{
    public Employe $employe;
    public float $scoreTotal = 0.0;
    public float $scoreCompetences = 0.0;
    public float $scorePerformance = 0.0;
    public float $scoreDisponibilite = 0.0;
    public float $scoreExperience = 0.0;
    public float $indiceConfiance = 0.0;
    public string $raisonRecommandation = '';

    public function getAppreciationKey(): string
    {
        if ($this->scoreTotal >= 80) return "ai.matching.perfect";
        if ($this->scoreTotal >= 60) return "ai.matching.good";
        if ($this->scoreTotal >= 40) return "ai.matching.fair";
        return "ai.matching.poor"; 
    }

    public function getConfianceKey(): string
    {
        if ($this->indiceConfiance >= 80) return "common.high";
        if ($this->indiceConfiance >= 50) return "common.medium";
        return "common.low";
    }

    public function getEmoji(): string
    {
        if ($this->scoreTotal >= 80) return "🌟";
        if ($this->scoreTotal >= 60) return "👍";
        return "⚠️";
    }

    public function getCouleur(): string
    {
        if ($this->scoreTotal >= 80) return "#27ae60";
        if ($this->scoreTotal >= 60) return "#cfb53b";
        return "#e74c3c";
    }
}

class MatchingService
{
    private EmployeRepository $employeRepository;
    private TacheRepository $tacheRepository;
    private PerformanceService $performanceService;
    private TranslatorInterface $translator;

    public function __construct(
        EmployeRepository $employeRepository,
        TacheRepository $tacheRepository,
        PerformanceService $performanceService,
        TranslatorInterface $translator
    )
    {
        $this->employeRepository = $employeRepository;
        $this->tacheRepository = $tacheRepository;
        $this->performanceService = $performanceService;
        $this->translator = $translator;
    }

    /**
     * @return RecommandationResult[]
     */
    public function recommanderEmployes(int $idTache, int $limit = 3): array
    {
        $tache = $this->tacheRepository->find($idTache);
        if (!$tache) return [];

        $agriculteurId = $tache->getIdAgriculteur();
        $employes = $this->employeRepository->findActifsByAgriculteur($agriculteurId);

        $results = [];
        foreach ($employes as $emp) {
            $rec = new RecommandationResult();
            $rec->employe = $emp;

            // 1. Performance
            $perf = $this->performanceService->calculatePerformance($emp->getId());
            $rec->scorePerformance = $perf['score'];
            $rec->scoreExperience = $perf['totalTaches'] > 0 ? 80.0 : 40.0;

            // 2. Compétences (approximation : poste vs catégorie tache)
            $rec->scoreCompetences = 50.0;
            $poste = strtolower($emp->getPoste() ?? '');
            $cat = strtolower($tache->getCategorie() ?? '');
            if ($poste !== '' && $cat !== '' && (str_contains($poste, $cat) || str_contains($cat, $poste))) {
                $rec->scoreCompetences = 90.0;
            }

            // 3. Disponibilité
            $nbEnCours = $this->tacheRepository->countTachesActivesParEmploye($emp->getId(), $agriculteurId);
            if ($nbEnCours === 0) {
                $rec->scoreDisponibilite = 100.0;
            } elseif ($nbEnCours <= 2) {
                $rec->scoreDisponibilite = 70.0;
            } else {
                $rec->scoreDisponibilite = 30.0;
            }

            // Calcul du score total
            $rec->scoreTotal = ($rec->scoreCompetences * 0.4) + ($rec->scorePerformance * 0.4) + ($rec->scoreDisponibilite * 0.2);
            $rec->indiceConfiance = min(100, $rec->scoreTotal + 10);
            
            $chargeKey = $nbEnCours === 0 ? 'common.none' : ($nbEnCours <= 2 ? 'common.moderate' : 'common.high_load');
            
            $rec->raisonRecommandation = $this->translator->trans('ai.matching.reason_detail', [
                '%name%' => $emp->getPrenom(),
                '%relevance%' => $rec->scoreCompetences,
                '%perf%' => $rec->scorePerformance,
                '%charge%' => $this->translator->trans($chargeKey)
            ]);

            $results[] = $rec;
        }

        // Tri par meilleur score
        usort($results, function (RecommandationResult $a, RecommandationResult $b) {
            return $b->scoreTotal <=> $a->scoreTotal;
        });

        return array_slice($results, 0, $limit);
    }
}
