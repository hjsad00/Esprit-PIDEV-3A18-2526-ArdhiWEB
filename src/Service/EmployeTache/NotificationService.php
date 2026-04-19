<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Notification;
use App\Repository\EmployeTache\NotificationRepository;
use App\Repository\EmployeTache\TacheRepository;
use App\Repository\EmployeTache\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Service de notifications — port PHP du NotificationService.java
 *
 * Gère :
 *  1. Tâches en retard      → TACHE_RETARD (CRITICAL)
 *  2. Tâches bloquées       → TACHE_BLOQUEE (WARNING)  [inactives depuis > 2 jours]
 *  3. Recommandations météo → via MeteoService
 */
class NotificationService
{
    public function __construct(
        private EntityManagerInterface  $em,
        private NotificationRepository  $notifRepo,
        private TacheRepository         $tacheRepo,
        private MeteoService            $meteoService,
        private TranslatorInterface     $translator,
        private EmployeRepository       $employeRepo,
    ) {}

    // ── Analyse principale ───────────────────────────────────────────────

    /**
     * Point d'entrée unique : analyse retards + météo pour un agriculteur.
     * À appeler à l'ouverture de la page notifications ou via Cron.
     */
    public function analyserNotifications(int $idAgriculteur): void
    {
        $this->analyserRetards($idAgriculteur);
        $this->analyserMeteo($idAgriculteur);
        $this->nettoyerObsoletes($idAgriculteur);
    }

    // ── 1. Tâches en retard ──────────────────────────────────────────────

    private function analyserRetards(int $idAgriculteur): void
    {
        $today  = new \DateTime('today');
        $taches = $this->tacheRepo->findByAgriculteur($idAgriculteur);

        foreach ($taches as $tache) {
            if ($this->estTerminee($tache)) continue;

            $dateFin = $tache->getDateFin();
            if (!$dateFin) continue;

            $finDate = $dateFin instanceof \DateTimeInterface
                ? \DateTime::createFromInterface($dateFin)
                : new \DateTime($dateFin);

            // Tâche en retard
            if ($finDate < $today) {
                if (!$this->notifRepo->existsTodayForTache(
                    Notification::TYPE_TACHE_RETARD,
                    $tache->getId(),
                    $idAgriculteur
                )) {
                    $jours = (int) $today->diff($finDate)->days;
                    $this->creer(
                        Notification::TYPE_TACHE_RETARD,
                        Notification::PRIORITE_CRITICAL,
                        $this->translator->trans('notification.types.task_overdue', ['%title%' => $tache->getTitre()]),
                        $this->translator->trans('notification.messages.overdue_detail', [
                            '%date%' => $finDate->format('d/m/Y'),
                            '%days%' => $jours
                        ]),
                        $idAgriculteur,
                        $tache->getId(),
                        $tache->getIdEmploye()
                    );
                }
            }

            // Tâche bloquée (En cours mais non modifiée depuis > 2 jours)
            if ($this->estEnCours($tache) && !$this->modificationRecente($tache)) {
                if (!$this->notifRepo->existsTodayForTache(
                    Notification::TYPE_TACHE_BLOQUEE,
                    $tache->getId(),
                    $idAgriculteur
                )) {
                    $this->creer(
                        Notification::TYPE_TACHE_BLOQUEE,
                        Notification::PRIORITE_WARNING,
                        $this->translator->trans('notification.types.task_blocked', ['%title%' => $tache->getTitre()]),
                        $this->translator->trans('notification.messages.blocked_detail'),
                        $idAgriculteur,
                        $tache->getId(),
                        $tache->getIdEmploye()
                    );
                }
            }
        }
    }

    // ── 2. Analyse météo ─────────────────────────────────────────────────

    private function analyserMeteo(int $idAgriculteur): void
    {
        try {
            $weather = $this->meteoService->getCurrentWeather();
            if (!$weather->isAvailable()) return;

            $taches = $this->tacheRepo->findTachesDuJour($idAgriculteur);

            foreach ($taches as $tache) {
                if ($this->estTerminee($tache)) continue;

                $recos = $this->meteoService->analyserConditionsPourTache($tache, $weather);

                foreach ($recos as $reco) {
                    // Anti-spam : 1 notification par type / tâche / jour
                    if ($this->notifRepo->existsTodayForTache(
                        $reco->getNotifType(),
                        $tache->getId(),
                        $idAgriculteur
                    )) continue;

                    [$priorite, $transKey] = match ($reco->getNiveau()) {
                        'POSITIVE' => [Notification::PRIORITE_INFO,     'meteo.alerts.good'],
                        'DANGER'   => [Notification::PRIORITE_CRITICAL, 'meteo.alerts.danger'],
                        default    => [Notification::PRIORITE_WARNING,  'meteo.alerts.caution'],
                    };

                    $this->creer(
                        $reco->getNotifType(),
                        $priorite,
                        $this->translator->trans($transKey, ['%task%' => $tache->getTitre(), '%reason%' => '']),
                        $reco->getMessage(),
                        $idAgriculteur,
                        $tache->getId(),
                        $tache->getIdEmploye()
                    );
                }
            }
        } catch (\Throwable $e) {
            // Météo indisponible → on continue silencieusement
        }
    }

    // ── 3. Nettoyage obsolètes ────────────────────────────────────────────

    /**
     * Supprime les notifications RETARD/BLOQUEE si la tâche est maintenant terminée.
     */
    private function nettoyerObsoletes(int $idAgriculteur): void
    {
        $notifications = $this->notifRepo->findByAgriculteur($idAgriculteur);
        $today = new \DateTime('today');

        foreach ($notifications as $notif) {
            if (!$notif->getIdTache()) continue;
            $tache = $this->tacheRepo->find($notif->getIdTache());
            if (!$tache) {
                $this->em->remove($notif);
                continue;
            }

            // Si la tâche est terminée → supprimer RETARD et BLOQUEE
            if ($this->estTerminee($tache) && in_array($notif->getType(), [
                Notification::TYPE_TACHE_RETARD,
                Notification::TYPE_TACHE_BLOQUEE,
            ])) {
                $this->em->remove($notif);
            }

            // Si la tâche bloquée a été modifiée récemment → supprimer la notif bloquée
            if ($notif->getType() === Notification::TYPE_TACHE_BLOQUEE
                && $this->modificationRecente($tache)
            ) {
                $this->em->remove($notif);
            }
        }

        $this->em->flush();
    }

    // ── CRUD ─────────────────────────────────────────────────────────────

    public function getByAgriculteur(int $idAgriculteur): array
    {
        return $this->notifRepo->findByAgriculteur($idAgriculteur);
    }

    public function countUnread(int $idAgriculteur): int
    {
        return $this->notifRepo->countUnread($idAgriculteur);
    }

    public function markAsRead(int $id): bool
    {
        $notif = $this->notifRepo->find($id);
        if (!$notif) return false;

        $notif->setLue(true);
        $notif->setDateLecture(new \DateTime());
        $this->em->flush();
        return true;
    }

    public function markAllAsRead(int $idAgriculteur): void
    {
        $unread = $this->notifRepo->findUnreadByAgriculteur($idAgriculteur);
        $now    = new \DateTime();
        foreach ($unread as $notif) {
            $notif->setLue(true);
            $notif->setDateLecture($now);
        }
        $this->em->flush();
    }

    public function delete(int $id): bool
    {
        $notif = $this->notifRepo->find($id);
        if (!$notif) return false;
        $this->em->remove($notif);
        $this->em->flush();
        return true;
    }

    // ── Helpers privés ────────────────────────────────────────────────────

    private function creer(
        string  $type,
        string  $priorite,
        string  $titre,
        string  $message,
        int     $idAgriculteur,
        ?int    $idTache   = null,
        ?int    $idEmploye = null
    ): void {
        // Précautions : si l'employé a été supprimé mais reste attaché à la tâche
        if ($idEmploye !== null && !$this->employeRepo->find($idEmploye)) {
            $idEmploye = null;
        }

        $notif = new Notification();
        $notif->setType($type)
              ->setPriorite($priorite)
              ->setTitre($titre)
              ->setMessage($message)
              ->setIdAgriculteur($idAgriculteur)
              ->setIdTache($idTache)
              ->setIdEmploye($idEmploye);

        $this->em->persist($notif);
        $this->em->flush();
    }

    private function estTerminee(object $tache): bool
    {
        $s = $tache->getStatut();
        return in_array($s, ['Terminé', 'Validé', 'Annulé'], true);
    }

    private function estEnCours(object $tache): bool
    {
        return $tache->getStatut() === 'En cours';
    }

    private function modificationRecente(object $tache): bool
    {
        if (!method_exists($tache, 'getDateModification')) return false;
        $modif = $tache->getDateModification();
        if (!$modif) return false;
        $limit = new \DateTime('-2 days');
        $modifDt = $modif instanceof \DateTimeInterface
            ? \DateTime::createFromInterface($modif)
            : new \DateTime($modif);
        return $modifDt > $limit;
    }
}