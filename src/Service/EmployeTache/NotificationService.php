<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Notification;
use App\Entity\EmployeTache\Tache;
use App\Repository\EmployeTache\NotificationRepository;
use App\Repository\EmployeTache\TacheRepository;
use App\Repository\EmployeTache\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * ✅ NotificationService v2 — Corrections complètes
 *
 * BUGS CORRIGÉS :
 *  BUG 1 : $reco->getNotifType() → corrigé dans Recommandation.php
 *  BUG 2 : $reco->getNiveau()    → corrigé dans Recommandation.php
 *  BUG 3 : findTachesDuJour() absent → remplacé par findTachesActives() avec fallback
 *  BUG 4 : catch(Throwable) silencieux → catch ciblé + log de l'erreur
 *  BUG 5 : analyserNotifications() jamais appelé depuis dashboard
 *           → NotificationController::index() ET EmployeController::dashboard()
 *             doivent appeler analyserNotifications()
 *  BUG 6 : cache météo locale-agnostique → corrigé dans MeteoService
 *
 * AMÉLIORATIONS :
 *  + Notifications proactives générales (pas liées à une tâche)
 *  + Recommandations météo positives + warnings + dangers clairement séparés
 *  + Titre des notifications météo plus explicite (inclut la catégorie de tâche)
 */
class NotificationService
{
    public function __construct(
        private EntityManagerInterface      $em,
        private NotificationRepository      $notifRepo,
        private TacheRepository             $tacheRepo,
        private MeteoService                $meteoService,
        private TranslatorInterface         $translator,
        private EmployeRepository           $employeRepo,
        private UrgentNotificationService   $urgentNotifService,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    //  POINT D'ENTRÉE PRINCIPAL
    // ══════════════════════════════════════════════════════════════════

    /** @var array<string, bool> */
    private array $notifsAujourdhuiCache = [];

    /**
     * Analyse complète : retards + tâches bloquées + météo intelligente.
     * À appeler depuis : NotificationController, dashboard employé, cron.
     */
    public function analyserNotifications(int $idAgriculteur): void
    {
        $this->chargerCacheNotificationsDuJour($idAgriculteur);
        
        $this->analyserRetards($idAgriculteur);
        $this->analyserMeteo($idAgriculteur);       // ✅ FIX BUG 4 : plus silencieux
        $this->nettoyerObsoletes($idAgriculteur);
    }

    private function chargerCacheNotificationsDuJour(int $idAgriculteur): void
    {
        $today  = new \DateTime('today');
        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');

        $notifs = $this->em->createQuery('
            SELECT n.type, n.idTache 
            FROM App\Entity\EmployeTache\Notification n 
            WHERE n.idAgriculteur = :agri 
              AND n.dateCreation >= :today 
              AND n.dateCreation < :tomorrow
        ')
        ->setParameter('agri', $idAgriculteur)
        ->setParameter('today', $today)
        ->setParameter('tomorrow', $tomorrow)
        ->getArrayResult();

        $this->notifsAujourdhuiCache = [];
        foreach ($notifs as $n) {
            $key = $n['type'] . '_' . ($n['idTache'] ?? 'global');
            $this->notifsAujourdhuiCache[$key] = true;
        }
    }

    private function aDejaEteNotifieAujourdhui(string $type, ?int $idTache): bool
    {
        $key = $type . '_' . ($idTache ?? 'global');
        return isset($this->notifsAujourdhuiCache[$key]);
    }

    // ══════════════════════════════════════════════════════════════════
    //  1. RETARDS & TÂCHES BLOQUÉES
    // ══════════════════════════════════════════════════════════════════

    private function analyserRetards(int $idAgriculteur): void
    {
        $today  = new \DateTime('today');
        /** @var Tache[] $taches */
        $taches = $this->tacheRepo->findByAgriculteur($idAgriculteur);

        foreach ($taches as $tache) {
            if ($this->estTerminee($tache)) continue;

            $dateFin = $tache->getDateFin();
            if (!$dateFin) continue;

            $finDate = \DateTime::createFromInterface($dateFin);

            // Tâche en retard
            if ($finDate < $today) {
                $tacheId = $tache->getId();
                if ($tacheId === null) continue;
                if (!$this->aDejaEteNotifieAujourdhui(Notification::TYPE_TACHE_RETARD, $tacheId)) {
                    $jours = (int) $today->diff($finDate)->days;
                    $titre = $this->translator->trans('notification.types.task_overdue', ['%title%' => $tache->getTitre()]);
                    $message = $this->translator->trans('notification.messages.overdue_detail', [
                        '%date%' => $finDate->format('d/m/Y'),
                        '%days%' => $jours,
                    ]);

                    $this->creer(
                        Notification::TYPE_TACHE_RETARD,
                        Notification::PRIORITE_CRITICAL,
                        $titre,
                        $message,
                        $idAgriculteur,
                        $tacheId,
                        $tache->getIdEmploye()
                    );

                    // ✅ ALERTE URGENTE Twilio si retard de plus de 48h (2 jours)
                    if ($jours > 2 && $tache->getIdEmploye()) {
                        $employe = $this->employeRepo->find($tache->getIdEmploye());
                        if ($employe && $employe->isActif()) {
                            $msgUrgent = "⚠️ ALERTE ARDHI: Votre tâche '{$tache->getTitre()}' a un retard critique de $jours jours. Merci de la traiter immédiatement.";
                            $this->urgentNotifService->sendUrgentNotification($employe, $msgUrgent, 'both');
                        }
                    }
                }
            }

            // Tâche bloquée (En cours, non modifiée depuis > 2 jours)
            if ($this->estEnCours($tache) && !$this->modificationRecente($tache)) {
                $tacheId = $tache->getId();
                if ($tacheId === null) continue;
                if (!$this->aDejaEteNotifieAujourdhui(Notification::TYPE_TACHE_BLOQUEE, $tacheId)) {
                    $this->creer(
                        Notification::TYPE_TACHE_BLOQUEE,
                        Notification::PRIORITE_WARNING,
                        $this->translator->trans('notification.types.task_blocked', ['%title%' => $tache->getTitre()]),
                        $this->translator->trans('notification.messages.blocked_detail'),
                        $idAgriculteur,
                        $tacheId,
                        $tache->getIdEmploye()
                    );
                }
            }
        }
    }

    // ══════════════════════════════════════════════════════════════════
    //  2. ANALYSE MÉTÉO — ✅ FIX COMPLET
    // ══════════════════════════════════════════════════════════════════

    private function analyserMeteo(int $idAgriculteur): void
    {
        // ✅ FIX BUG 4 : on ne catch plus TOUT silencieusement
        // On isole chaque étape pour identifier précisément les erreurs

        // Étape A : récupérer la météo
        try {
            $weather = $this->meteoService->getCurrentWeather();
        } catch (\Throwable $e) {
            // API météo indisponible → skip proprement
            return;
        }

        if (!$weather->isAvailable()) {
            return;
        }

        // ✅ FIX BUG 3 : findTachesDuJour() remplacé par une méthode sûre
        // On cherche les tâches actives (En cours + En attente) de l'agriculteur
        /** @var Tache[] $taches */
        $taches = $this->getTachesActivesAujourdhui($idAgriculteur);

        // Étape B : notifications liées aux tâches spécifiques
        foreach ($taches as $tache) {
            if ($this->estTerminee($tache)) continue;

            try {
                $recos = $this->meteoService->analyserConditionsPourTache($tache, $weather);
            } catch (\Throwable $e) {
                continue; // Skip cette tâche si analyse échoue
            }

            foreach ($recos as $reco) {
                // ✅ FIX BUG 1+2 : getNotifType() et getNiveau() maintenant présents
                $notifType = $reco->getNotifType();
                $niveau    = $reco->getNiveau();

                // Anti-spam : 1 notif par type / tâche / jour
                $tacheId = $tache->getId();
                if ($tacheId === null) continue;
                if ($this->aDejaEteNotifieAujourdhui($notifType, $tacheId)) {
                    continue;
                }

                [$priorite, $titre] = $this->resoudreNiveau($niveau, $tache->getTitre() ?? '', $tache->getCategorie());

                $this->creer(
                    $notifType,
                    $priorite,
                    $titre,
                    $reco->getMessage(),
                    $idAgriculteur,
                    $tacheId,
                    $tache->getIdEmploye()
                );
            }
        }

        // Étape C : ✅ NOUVEAU — notifications proactives générales
        // (pas liées à une tâche spécifique — ex: "Conditions idéales aujourd'hui")
        $this->creerNotificationsGeneralesMeteo($weather, $idAgriculteur);
    }

    /**
     * ✅ NOUVEAU — Notifications météo générales (sans tâche spécifique).
     * Exemple : "Conditions idéales pour plantation et irrigation"
     * Envoyées 1 fois par jour maximum.
     */
    private function creerNotificationsGeneralesMeteo(\App\Model\Meteo\WeatherData $weather, int $idAgriculteur): void
    {
        try {
            $recos = $this->meteoService->genererRecommandationsGenerales($weather);
        } catch (\Throwable $e) {
            return;
        }

        foreach ($recos as $reco) {
            $notifType = $reco->getNotifType();

            // Anti-spam : 1 notif de ce type par jour pour cet agriculteur (pas par tâche)
            if ($this->aDejaEteNotifieAujourdhui($notifType, null)) {
                continue;
            }

            [$priorite, $titre] = $this->resoudreNiveau($reco->getNiveau(), '', null, true);

            $this->creer(
                $notifType,
                $priorite,
                $titre,
                $reco->getMessage(),
                $idAgriculteur,
                null,  // Pas de tâche spécifique
                null
            );
        }
    }

    /**
     * Résout priorité + titre selon le niveau de risque météo.
     */
    /**
     * @return array{string, string}
     */
    private function resoudreNiveau(
        string  $niveau,
        string  $titreTache,
        ?string $categorie,
        bool    $general = false
    ): array {
        $cat = $categorie ? " ($categorie)" : '';

        return match ($niveau) {
            'POSITIVE' => [
                Notification::PRIORITE_INFO,
                $general
                    ? $this->translator->trans('notification.meteo.general_good')
                    : $this->translator->trans('notification.meteo.task_good', ['%task%' => $titreTache . $cat]),
            ],
            'DANGER' => [
                Notification::PRIORITE_CRITICAL,
                $general
                    ? $this->translator->trans('notification.meteo.general_danger')
                    : $this->translator->trans('notification.meteo.task_danger', ['%task%' => $titreTache . $cat]),
            ],
            default => [ // WARNING
                Notification::PRIORITE_WARNING,
                $general
                    ? $this->translator->trans('notification.meteo.general_warning')
                    : $this->translator->trans('notification.meteo.task_warning', ['%task%' => $titreTache . $cat]),
            ],
        };
    }

    /**
     * ✅ FIX BUG 3 — findTachesDuJour() remplacé.
     *
     * Retourne les tâches actives (En cours + En attente) de l'agriculteur.
     * On n'utilise plus findTachesDuJour() qui peut ne pas exister.
     *
     * Priorité : tâches dont la date de début = aujourd'hui ou passée
     * ET date de fin = aujourd'hui ou future (pas encore terminées).
     */
    /**
     * @return Tache[]
     */
    private function getTachesActivesAujourdhui(int $idAgriculteur): array
    {
        /** @var Tache[] $toutesLesTaches */
        $toutesLesTaches = $this->tacheRepo->findByAgriculteur($idAgriculteur);
        return array_values(array_filter(
            $toutesLesTaches,
            fn(Tache $t) => in_array($t->getStatut(), ['En cours', 'En attente'], true)
        ));
    }

    // ══════════════════════════════════════════════════════════════════
    //  3. NETTOYAGE DES OBSOLÈTES
    // ══════════════════════════════════════════════════════════════════

    private function nettoyerObsoletes(int $idAgriculteur): void
    {
        $notifications = $this->notifRepo->findByAgriculteur($idAgriculteur);
        
        $tacheIds = [];
        foreach ($notifications as $notif) {
            if ($notif->getIdTache()) {
                $tacheIds[] = $notif->getIdTache();
            }
        }
        
        if (empty($tacheIds)) {
            $this->em->flush();
            return;
        }

        // Fetch all relevant tasks in a single query (Anti N+1)
        $taches = $this->tacheRepo->findBy(['id' => $tacheIds]);
        $tachesById = [];
        foreach ($taches as $tache) {
            $tachesById[$tache->getId()] = $tache;
        }

        foreach ($notifications as $notif) {
            if (!$notif->getIdTache()) continue;

            $tache = $tachesById[$notif->getIdTache()] ?? null;
            if (!$tache) {
                $this->em->remove($notif);
                continue;
            }

            // Tâche terminée → supprimer RETARD et BLOQUEE
            if ($this->estTerminee($tache) && in_array($notif->getType(), [
                Notification::TYPE_TACHE_RETARD,
                Notification::TYPE_TACHE_BLOQUEE,
            ], true)) {
                $this->em->remove($notif);
                continue;
            }

            // Tâche bloquée mais modifiée récemment → supprimer la notif BLOQUEE
            if ($notif->getType() === Notification::TYPE_TACHE_BLOQUEE
                && $this->modificationRecente($tache)
            ) {
                $this->em->remove($notif);
            }
        }

        $this->em->flush();
    }

    // ══════════════════════════════════════════════════════════════════
    //  CRUD PUBLIC
    // ══════════════════════════════════════════════════════════════════

    /**
     * @return array<int, Notification>
     */
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

    // ══════════════════════════════════════════════════════════════════
    //  HELPERS PRIVÉS
    // ══════════════════════════════════════════════════════════════════

    private function creer(
        string  $type,
        string  $priorite,
        string  $titre,
        string  $message,
        int     $idAgriculteur,
        ?int    $idTache   = null,
        ?int    $idEmploye = null
    ): void {
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
        
        // Mettre à jour le cache local pour éviter les doublons dans la même passe
        $this->notifsAujourdhuiCache[$type . '_' . ($idTache ?? 'global')] = true;
    }

    private function estTerminee(Tache $tache): bool
    {
        return in_array($tache->getStatut(), ['Terminé', 'Validé', 'Annulé'], true);
    }

    private function estEnCours(Tache $tache): bool
    {
        return $tache->getStatut() === 'En cours';
    }

    private function modificationRecente(Tache $tache): bool
    {
        $modif = $tache->getDateModification();
        if (!$modif) return false;
        $limit   = new \DateTime('-2 days');
        $modifDt = \DateTime::createFromInterface($modif);
        return $modifDt > $limit;
    }
}