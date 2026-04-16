<?php

namespace App\Command;

use App\Entity\MaterielEtMaintenance\Materiel;
use App\Repository\MaterielEtMaintenance\MaintenanceRepository;
use App\Repository\MaterielEtMaintenance\MaterielRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Workflow\WorkflowInterface;

#[AsCommand(
    name: 'app:maintenance:check-triggers',
    description: 'Vérifie les dates et heures de maintenance pour déclencher les rappels automatiques.',
)]
class CheckMaintenanceTriggersCommand extends Command
{
    private MaterielRepository $materielRepository;
    private MaintenanceRepository $maintenanceRepository;
    private EntityManagerInterface $entityManager;
    private WorkflowInterface $materielLifecycleStateMachine;

    public function __construct(
        MaterielRepository $materielRepository,
        MaintenanceRepository $maintenanceRepository,
        EntityManagerInterface $entityManager,
        WorkflowInterface $materielLifecycleStateMachine
    ) {
        parent::__construct();
        $this->materielRepository = $materielRepository;
        $this->maintenanceRepository = $maintenanceRepository;
        $this->entityManager = $entityManager;
        $this->materielLifecycleStateMachine = $materielLifecycleStateMachine;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Vérification des déclencheurs de maintenance...');

        $now = new \DateTime();
        $todayStart = (clone $now)->setTime(0, 0, 0);
        $todayEnd = (clone $now)->setTime(23, 59, 59);

        // 1. Passage automatique en "Maintenance" pour les interventions prévues AUJOURD'HUI
        $io->section('Vérification du planning (Interventions du jour)');
        $maintenancesToday = $this->maintenanceRepository->createQueryBuilder('m')
            ->andWhere('m.date_maintenance BETWEEN :start AND :end')
            ->andWhere('m.statut_maintenance = :statut')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->setParameter('statut', 'planifiee')
            ->getQuery()
            ->getResult();

        $planCount = 0;
        foreach ($maintenancesToday as $maint) {
            $materiel = $maint->getMateriel();
            if ($this->materielLifecycleStateMachine->can($materiel, 'mettre_en_maintenance')) {
                $this->materielLifecycleStateMachine->apply($materiel, 'mettre_en_maintenance');
                $maint->setStatutMaintenance('en_cours'); // On passe l'intervention en cours aussi
                $io->text(sprintf('➤ <info>%s</info> : Basculement automatique en maintenance (Rendez-vous aujourd\'hui)', $materiel->getNom()));
                $planCount++;
            }
        }

        // 2. Vérification des seuils (Date et Heures) pour les matériels en service
        $io->section('Vérification des seuils (Temps et Heures d\'utilisation)');
        $materiels = $this->materielRepository->findBy(['statut' => 'en_service']);
        $thresholdCount = 0;

        foreach ($materiels as $materiel) {
            $triggerDate = false;
            $triggerHours = false;

            // 1. Vérification de la date
            if ($materiel->getDateProchaineMaintenance() && $materiel->getDateProchaineMaintenance() <= $now) {
                $triggerDate = true;
            }

            // 2. Vérification des heures (Relative à la dernière maintenance)
            $heuresDepuisDerniere = $materiel->getHeuresUtilisation() - $materiel->getDerniereMaintenanceHeures();
            if ($heuresDepuisDerniere >= $materiel->getSeuilMaintenanceHeures()) {
                $triggerHours = true;
            }

            if ($triggerDate || $triggerHours) {
                // On ne bascule plus AUTOMATIQUEMENT ici car l'utilisateur veut planifier lui-même.
                // On pourrait ajouter une notification ici.
                $io->text(sprintf(
                    '⚠️ <comment>%s</comment> : Seuil atteint (%s). L\'agriculteur doit planifier une maintenance.',
                    $materiel->getNom(),
                    $triggerDate ? 'Date conseillée' : 'Heures atteintes'
                ));
            }
        }

        $this->entityManager->flush();

        if ($planCount > 0 || $thresholdCount > 0) {
            $io->success(sprintf('Action terminée : %d planning(s) et %d seuil(s) traités.', $planCount, $thresholdCount));
        } else {
            $io->info('Aucun matériel ne nécessite de changement de statut pour le moment.');
        }

        return Command::SUCCESS;
    }
}
