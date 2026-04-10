<?php

namespace App\Service\EmployeTache;

use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;

class PerformanceData
{
    public int $idEmploye;
    public ?string $nomEmploye = null;
    public int $totalTaches = 0;
    public int $tachesTerminees = 0;
    public float $tauxReussite = 0.0;
    public float $score = 0.0;

    public function getAppreciation(): string
    {
        if ($this->score >= 80) return "Excellent";
        if ($this->score >= 60) return "Bon";
        if ($this->score >= 40) return "Moyen";
        return "À améliorer";
    }

    public function getEmoji(): string
    {
        if ($this->score >= 80) return "🌟";
        if ($this->score >= 60) return "👍";
        if ($this->score >= 40) return "🤔";
        return "⚠️";
    }

    public function getCouleur(): string
    {
        if ($this->score >= 80) return "#27ae60";
        if ($this->score >= 60) return "#3498db";
        if ($this->score >= 40) return "#f39c12";
        return "#e74c3c";
    }
}

class PerformanceService
{
    private EmployeRepository $employeRepository;
    private TacheRepository $tacheRepository;

    public function __construct(EmployeRepository $employeRepository, TacheRepository $tacheRepository)
    {
        $this->employeRepository = $employeRepository;
        $this->tacheRepository = $tacheRepository;
    }

    public function calculatePerformance(int $idEmploye): PerformanceData
    {
        $data = new PerformanceData();
        $data->idEmploye = $idEmploye;

        $taches = $this->tacheRepository->findBy(['idEmploye' => $idEmploye]);
        
        $terminees = 0;
        foreach ($taches as $t) {
            $statut = strtolower($t->getStatut());
            if (in_array($statut, ['terminé', 'terminee', 'validé', 'validee'])) {
                $terminees++;
            }
        }

        $data->totalTaches = count($taches);
        $data->tachesTerminees = $terminees;

        if ($data->totalTaches > 0) {
            $data->tauxReussite = ($data->tachesTerminees / $data->totalTaches) * 100;
        }

        // Score formula: mostly complete tasks + base score
        $data->score = 30 + ($data->tauxReussite * 0.7);
        if ($data->totalTaches === 0) {
            $data->score = 25.0; // beginner score
        }
        
        if ($data->score > 100) $data->score = 100;

        return $data;
    }

    /**
     * @return PerformanceData[]
     */
    public function getClassement(int $idAgriculteur): array
    {
        $employes = $this->employeRepository->findBy(['idAgriculteur' => $idAgriculteur, 'actif' => true]);
        
        $classement = [];
        foreach ($employes as $e) {
            $perf = $this->calculatePerformance($e->getId());
            $perf->nomEmploye = trim($e->getPrenom() . ' ' . $e->getNom());
            $classement[] = $perf;
        }

        usort($classement, function (PerformanceData $a, PerformanceData $b) {
            return $b->score <=> $a->score;
        });

        return $classement;
    }
}
