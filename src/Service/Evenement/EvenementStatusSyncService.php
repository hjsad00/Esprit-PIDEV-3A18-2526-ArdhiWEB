<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Evenement;
use App\Repository\Evenement\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;

class EvenementStatusSyncService
{
    public function __construct(
        private EvenementRepository $evenementRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Sync all events statuses from dates.
     * Returns number of updated rows in memory (then flushed once).
     */
    public function syncAll(): int
    {
        $today = new \DateTimeImmutable('today');
        $updated = 0;

        foreach ($this->evenementRepository->findForStatusSync() as $evenement) {
            if ($this->syncOne($evenement, $today, false)) {
                $updated++;
            }
        }

        if ($updated > 0) {
            $this->entityManager->flush();
        }

        return $updated;
    }

    /**
     * Sync one event status from dates.
     */
    public function syncOne(Evenement $evenement, ?\DateTimeImmutable $today = null, bool $flush = true): bool
    {
        if ($evenement->getStatut() === 'ANNULE' || !$evenement->getDateDebut() || !$evenement->getDateFin()) {
            return false;
        }

        $today = $today ?? new \DateTimeImmutable('today');
        $start = \DateTimeImmutable::createFromInterface($evenement->getDateDebut())->setTime(0, 0, 0);
        $end = \DateTimeImmutable::createFromInterface($evenement->getDateFin())->setTime(0, 0, 0);

        $newStatus = $today < $start
            ? 'A_VENIR'
            : ($today > $end ? 'TERMINE' : 'EN_COURS');

        if ($evenement->getStatut() === $newStatus) {
            return false;
        }

        $evenement->setStatut($newStatus);
        if ($flush) {
            $this->entityManager->flush();
        }

        return true;
    }
}

