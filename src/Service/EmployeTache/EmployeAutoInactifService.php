<?php

namespace App\Service\EmployeTache;

use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service d'inactivation automatique des employés sans tâche active.
 *
 * Règle métier :
 *   Un employé est automatiquement désactivé (actif = false) si
 *   il n'a AUCUNE tâche avec statut En attente, En cours.
 *   Il est réactivé dès qu'une tâche active lui est assignée.
 */
class EmployeAutoInactifService
{
    /** @phpstan-ignore classConstant.unused */
    private const STATUTS_ACTIFS = ['En attente', 'En cours'];

    public function __construct(
        private EntityManagerInterface $em,
        private EmployeRepository      $employeRepo,
        private TacheRepository        $tacheRepo,
    ) {}

    /**
     * Vérifie et met à jour le statut actif/inactif de TOUS les employés
     * d'un agriculteur. Appelée à chaque changement de tâche.
     *
     * @return array{actives: int, desactives: int} Résumé des changements
     */
    public function synchroniserStatuts(int $idAgriculteur): array
    {
        $employes  = $this->employeRepo->findByAgriculteur($idAgriculteur);
        
        $actifsBatch = $this->tacheRepo->countTachesActivesBatch($idAgriculteur);
        $mapActifs = [];
        foreach ($actifsBatch as $dto) {
            $mapActifs[(int) $dto->key] = $dto->total > 0;
        }

        $actives   = 0;
        $desactives = 0;

        foreach ($employes as $employe) {
            $aTacheActive = $mapActifs[(int) $employe->getId()] ?? false;

            if ($aTacheActive && !$employe->isActif()) {
                // Réactivation automatique
                $employe->setActif(true);
                $actives++;
            } elseif (!$aTacheActive && $employe->isActif()) {
                // Désactivation automatique
                $employe->setActif(false);
                $desactives++;
            }
        }

        if ($actives > 0 || $desactives > 0) {
            $this->em->flush();
        }

        return ['actives' => $actives, 'desactives' => $desactives];
    }

    /**
     * Vérifie et met à jour le statut d'UN SEUL employé.
     * Plus rapide — appelée après une action ciblée sur une tâche.
     */
    public function synchroniserEmploye(int $idEmploye, int $idAgriculteur): void
    {
        $employe = $this->employeRepo->find($idEmploye);
        if (!$employe || $employe->getIdAgriculteur() !== $idAgriculteur) return;

        $aTacheActive = $this->aTacheActive($idEmploye, $idAgriculteur);

        if ($aTacheActive && !$employe->isActif()) {
            $employe->setActif(true);
            $this->em->flush();
        } elseif (!$aTacheActive && $employe->isActif()) {
            $employe->setActif(false);
            $this->em->flush();
        }
    }

    /**
     * Vérifie si un employé a au moins une tâche active.
     */
    private function aTacheActive(int $idEmploye, int $idAgriculteur): bool
    {
        return $this->tacheRepo->countTachesActivesParEmploye($idEmploye, $idAgriculteur) > 0;
    }
}