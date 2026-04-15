<?php

namespace App\EventSubscriber;

use App\Entity\MaterielEtMaintenance\Maintenance;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Symfony\Component\Workflow\WorkflowInterface;

class MaintenanceSyncSubscriber implements EventSubscriber
{
    private WorkflowInterface $materielLifecycleStateMachine;

    public function __construct(WorkflowInterface $materielLifecycleStateMachine)
    {
        $this->materielLifecycleStateMachine = $materielLifecycleStateMachine;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        // On vérifie les entités créées
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            $this->sync($entity, $em);
        }

        // On vérifie les entités mises à jour
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            $this->sync($entity, $em);
        }
    }

    private function sync(object $entity, $em): void
    {
        if (!$entity instanceof Maintenance) {
            return;
        }

        $materiel = $entity->getMateriel();
        if (!$materiel) {
            return;
        }

        $statutMaint = $entity->getStatutMaintenance();
        $uow = $em->getUnitOfWork();

        $changed = false;

        // 1. Si la maintenance est mise "En cours" -> On bascule le matériel en "Maintenance"
        if ($statutMaint === 'en_cours') {
            if ($this->materielLifecycleStateMachine->can($materiel, 'mettre_en_maintenance')) {
                $this->materielLifecycleStateMachine->apply($materiel, 'mettre_en_maintenance');
                $changed = true;
            }
        }

        // 2. Si la maintenance est mise "Terminée" -> On bascule le matériel en "Service"
        if ($statutMaint === 'terminee') {
            if ($this->materielLifecycleStateMachine->can($materiel, 'confirmer_maintenance')) {
                $this->materielLifecycleStateMachine->apply($materiel, 'confirmer_maintenance');
                $changed = true;
            }
        }

        // 3. IMPORTANT : Forcer Doctrine à voir le changement sur le Matériel
        if ($changed) {
            $uow->recomputeSingleEntityChangeSet(
                $em->getClassMetadata(get_class($materiel)),
                $materiel
            );
        }
    }
}
