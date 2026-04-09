<?php

namespace App\Service\EmployeTache;

use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;

class RhChatbotService
{
    public function __construct(
        private PerformanceService $performanceService,
        private EmployeRepository  $employeRepo,
        private TacheRepository    $tacheRepo
    ) {}

    public function processMessage(string $message, int $idAgriculteur): array
    {
        $message = strtolower(trim($message));

        // Intents
        if ($this->isIntent(['recommander', 'recommande', 'meilleur', 'recommandation'], $message)) {
            return $this->getRecommandationResponse($idAgriculteur);
        }

        if ($this->isIntent(['comparer', 'top 3', 'top3', 'classement'], $message)) {
            return $this->getTop3Response($idAgriculteur);
        }

        if ($this->isIntent(['disponibilites', 'disponible', 'dispo', 'libre'], $message)) {
            return $this->getDisponibilitesResponse($idAgriculteur);
        }

        if ($this->isIntent(['performances', 'performance', 'statistiques'], $message)) {
            return $this->getPerformancesResponse($idAgriculteur);
        }

        if ($this->isIntent(['bonjour', 'salut', 'coucou', 'hello'], $message)) {
            return $this->getGreetingResponse();
        }

        return [
            'type' => 'text',
            'text' => "Je ne suis pas sûr de comprendre. Pouvez-vous utiliser des mots-clés comme **recommander**, **top 3**, **disponibilités** ou **performances** ?"
        ];
    }

    private function isIntent(array $keywords, string $message): bool
    {
        foreach ($keywords as $kw) {
            if (str_contains($message, $kw)) {
                return true;
            }
        }
        return false;
    }

    private function getGreetingResponse(): array
    {
        return [
            'type' => 'text',
            'text' => "Bonjour ! Je suis votre assistant RH local. Je peux vous :\n\n- 🎯 **Recommander un employé** pour vos tâches\n- 🏆 **Comparer le Top 3** candidats\n- 📅 Vérifier les **disponibilités**\n- 📊 Analyser les **performances** de votre équipe\n\nQue puis-je faire pour vous ?"
        ];
    }

    private function getRecommandationResponse(int $idAgriculteur): array
    {
        $classement = $this->performanceService->getClassement($idAgriculteur);

        if (empty($classement)) {
            return [
                'type' => 'text',
                'text' => "Aucun employé actif trouvé pour vous faire une recommandation."
            ];
        }

        $best = $classement[0];
        $employe = $this->employeRepo->find($best['idEmploye']);

        return [
            'type' => 'employee_card',
            'text' => "Basé sur les performances globales, voici ma meilleure recommandation :",
            'employees' => [$this->formatEmployeeCard($employe, $best)]
        ];
    }

    private function getTop3Response(int $idAgriculteur): array
    {
        $classement = $this->performanceService->getClassement($idAgriculteur);

        if (empty($classement)) {
            return [
                'type' => 'text',
                'text' => "Aucun employé actif trouvé."
            ];
        }

        $top3 = array_slice($classement, 0, 3);
        $employeesData = [];

        foreach ($top3 as $perf) {
            $emp = $this->employeRepo->find($perf['idEmploye']);
            if ($emp) {
                $employeesData[] = $this->formatEmployeeCard($emp, $perf);
            }
        }

        return [
            'type' => 'employee_list',
            'text' => "Voici le Top " . count($employeesData) . " de votre équipe selon leurs performances :",
            'employees' => $employeesData
        ];
    }

    private function getDisponibilitesResponse(int $idAgriculteur): array
    {
        $employes = $this->employeRepo->findActifsByAgriculteur($idAgriculteur);
        
        if (empty($employes)) {
            return [
                'type' => 'text',
                'text' => "Aucun employé actif n'a pu être trouvé."
            ];
        }

        $dispos = [];
        foreach ($employes as $emp) {
            // Count 'En cours' tasks
            $taches = $this->tacheRepo->findBy(['idEmploye' => $emp->getId(), 'statut' => 'En cours']);
            $enCoursCount = count($taches);
            
            $status = $enCoursCount == 0 ? '🟢 Disponible' : '🟠 Occupé (' . $enCoursCount . ' en cours)';
            $color = $enCoursCount == 0 ? '#27ae60' : '#f39c12';
            
            // On ordonne pour mettre les dispos en premier
            $dispos[] = [
                'emp' => $emp,
                'statusLabel' => $status,
                'color' => $color,
                'count' => $enCoursCount,
                'data' => [
                    'id' => $emp->getId(),
                    'name' => $emp->getNomComplet(),
                    'poste' => $emp->getPoste(),
                    'photo' => $emp->getPhotoPath(),
                    'email' => $emp->getEmail(),
                    'statusLabel' => $status,
                    'color' => $color
                ]
            ];
        }

        // Sort by number of active tasks ASC
        usort($dispos, fn($a, $b) => $a['count'] <=> $b['count']);

        // Limit to top 5 for chat size
        $topDispos = array_slice($dispos, 0, 5);
        $finalData = array_map(fn($item) => $item['data'], $topDispos);

        return [
            'type' => 'availability_list',
            'text' => "Voici les employés les plus disponibles actuellement :",
            'employees' => $finalData
        ];
    }

    private function getPerformancesResponse(int $idAgriculteur): array
    {
        $classement = $this->performanceService->getClassement($idAgriculteur);
        $kpis = $this->performanceService->getStatistiquesGlobales($classement);

        return [
            'type' => 'performance_summary',
            'text' => "Voici un aperçu des performances de votre équipe :",
            'stats' => [
                'moyenne' => $kpis['moyenneScore'],
                'meilleur' => $kpis['meilleurEmploye'],
                'totalTaches' => $kpis['totalTaches']
            ]
        ];
    }

    private function formatEmployeeCard($employe, array $perf): array
    {
        return [
            'id' => $employe->getId(),
            'name' => $employe->getNomComplet(),
            'poste' => $employe->getPoste(),
            'photo' => $employe->getPhotoPath(),
            'email' => $employe->getEmail(),
            'score' => $perf['score'],
            'taux' => $perf['tauxReussite'],
            'appreciation' => $perf['emoji'] . ' ' . $perf['appreciation'],
            'color' => $perf['couleur']
        ];
    }
}
