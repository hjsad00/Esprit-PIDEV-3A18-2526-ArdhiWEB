<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Tache;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;

/**
 * Service Google Calendar — Service Account (traduction exacte du Java)
 *
 * Même logique que GoogleCalendarService.java :
 *   - Utilise un Service Account (credentials.json)
 *   - Écrit dans le calendrier de l'utilisateur (email partagé)
 *   - CRUD : créer, mettre à jour, supprimer événements
 *   - Couleur selon priorité (identique au Java)
 *
 * Prérequis :
 *   composer require google/apiclient
 *
 * Setup (identique au Java) :
 *   1. Google Calendar → Paramètres → Partager avec le Service Account
 *   2. Mettre credentials.json dans config/google/credentials.json
 *   3. Configurer GOOGLE_CALENDAR_ID dans .env
 */
class GoogleCalendarService
{
    private const APPLICATION_NAME  = 'Ardhi - Gestion Employés';
    private const SCOPES             = [Calendar::CALENDAR];

    private ?Calendar $calendarService = null;
    private bool      $connected       = false;
    private string    $lastError       = '';
    private string    $accountEmail    = '';

    // Identique à calendarId Java — l'email Gmail dont le calendrier est partagé
    private string $calendarId;

    // Chemin vers le credentials.json du Service Account
    private string $credentialsPath;

    public function __construct(string $projectDir, string $calendarId)
    {
        // credentials.json dans config/google/ (gitignore recommandé)
        $this->credentialsPath = $projectDir . '/config/google/credentials.json';
        $this->calendarId      = $calendarId;
    }

    // ── Connexion Service Account ─────────────────────────────────────
    // Identique à GoogleCalendarService.connecter() Java

    public function connecter(): bool
    {
        if ($this->connected && $this->calendarService !== null) return true;

        try {
            if (!file_exists($this->credentialsPath)) {
                $this->lastError = 'credentials.json introuvable : ' . $this->credentialsPath
                    . "\nPlacer dans : config/google/credentials.json";
                error_log('[GCal] ❌ ' . $this->lastError);
                return false;
            }

            $client = new Client();
            $client->setApplicationName(self::APPLICATION_NAME);
            $client->setScopes(self::SCOPES);
            $client->setAuthConfig($this->credentialsPath);

            // Lire l'email du Service Account depuis credentials.json
            $credsContent = file_get_contents($this->credentialsPath);
            $creds = $credsContent !== false ? json_decode($credsContent, true) : [];
            $this->accountEmail = is_array($creds) ? ($creds['client_email'] ?? '') : '';

            $this->calendarService = new Calendar($client);
            $this->connected       = true;

            error_log('[GCal] ✅ Service Account connecté : ' . $this->accountEmail);
            error_log('[GCal] 📅 Calendrier cible : ' . $this->calendarId);
            return true;

        } catch (\Exception $e) {
            $this->lastError = 'Erreur connexion : ' . $e->getMessage();
            error_log('[GCal] ❌ ' . $this->lastError);
            return false;
        }
    }

    public function isConnected(): bool    { return $this->connected && $this->calendarService !== null; }
    public function getLastError(): string { return $this->lastError; }
    public function getAccountEmail(): string { return $this->accountEmail; }
    public function getCalendarId(): string   { return $this->calendarId; }
    public function setCalendarId(string $id): void { $this->calendarId = $id; }

    public function deconnecter(): void
    {
        $this->connected       = false;
        $this->calendarService = null;
    }

    // ── CRUD Événements ───────────────────────────────────────────────
    // Identiques aux méthodes Java

    /**
     * Crée un événement dans Google Calendar — identique à creerEvenement() Java
     * Retourne l'ID Google de l'événement créé, ou null si erreur
     */
    public function creerEvenement(Tache $tache): ?string
    {
        if (!$this->isConnected() || $this->calendarService === null) return null;
        try {
            $event = $this->tacheVersEvent($tache);
            $created = $this->calendarService->events->insert($this->calendarId, $event);

            error_log('[GCal] ✅ Créé dans "' . $this->calendarId . '" : '
                . $created->getSummary() . ' | ID: ' . $created->getId());

            return $created->getId();

        } catch (\Exception $e) {
            $this->lastError = 'Erreur création : ' . $e->getMessage();
            error_log('[GCal] ❌ ' . $this->lastError);

            if (str_contains($e->getMessage(), '404')) {
                error_log('[GCal] ⚠️ Calendrier "' . $this->calendarId . '" introuvable.');
                error_log('[GCal]    → Vérifier partage avec : ' . $this->accountEmail);
            } elseif (str_contains($e->getMessage(), '403')) {
                error_log('[GCal] ⚠️ Accès refusé — partager le calendrier avec : ' . $this->accountEmail);
            }
            return null;
        }
    }

    /**
     * Met à jour un événement — identique à mettreAJourEvenement() Java
     */
    public function mettreAJourEvenement(string $googleEventId, Tache $tache): bool
    {
        if (!$this->isConnected() || $this->calendarService === null || !$googleEventId) return false;
        try {
            $event = $this->tacheVersEvent($tache);
            $this->calendarService->events->update($this->calendarId, $googleEventId, $event);
            error_log('[GCal] ✅ Mis à jour : ' . $googleEventId);
            return true;
        } catch (\Exception $e) {
            $this->lastError = 'Erreur MAJ : ' . $e->getMessage();
            error_log('[GCal] ❌ ' . $this->lastError);
            return false;
        }
    }

    /**
     * Supprime un événement — identique à supprimerEvenement() Java
     */
    public function supprimerEvenement(string $googleEventId): bool
    {
        if (!$this->isConnected() || $this->calendarService === null || !$googleEventId) return false;
        try {
            $this->calendarService->events->delete($this->calendarId, $googleEventId);
            error_log('[GCal] ✅ Supprimé : ' . $googleEventId);
            return true;
        } catch (\Exception $e) {
            $this->lastError = 'Erreur suppression : ' . $e->getMessage();
            error_log('[GCal] ❌ ' . $this->lastError);
            return false;
        }
    }

    /**
     * Récupère les événements d'une période — identique à getEvenements() Java
     *
     * @return array<int, mixed>
     */
    public function getEvenements(\DateTimeInterface $debut, \DateTimeInterface $fin): array
    {
        if (!$this->isConnected() || $this->calendarService === null) return [];
        try {
            $finDt = \DateTime::createFromInterface($fin);
            $events = $this->calendarService->events->listEvents($this->calendarId, [
                'timeMin'      => $debut->format(\DateTime::RFC3339),
                'timeMax'      => $finDt->modify('+1 day')->format(\DateTime::RFC3339),
                'orderBy'      => 'startTime',
                'singleEvents' => true,
            ]);
            $items = $events->getItems();
            error_log('[GCal] ' . count($items) . ' événement(s) dans "' . $this->calendarId . '"');
            return $items;
        } catch (\Exception $e) {
            $this->lastError = 'Erreur récupération : ' . $e->getMessage();
            error_log('[GCal] ❌ ' . $this->lastError);
            return [];
        }
    }

    /**
     * Retourne le nom du calendrier — identique à getNomCalendrier() Java
     */
    public function getNomCalendrier(): ?string
    {
        if (!$this->isConnected() || $this->calendarService === null) return null;
        try {
            return $this->calendarService->calendars->get($this->calendarId)->getSummary();
        } catch (\Exception $e) {
            error_log('[GCal] getNom: ' . $e->getMessage());
            return '⬤ ' . $this->calendarId;
        }
    }

    // ── Conversion Tache → Google Event ──────────────────────────────
    // Traduction exacte de tacheVersEvent() Java

    private function tacheVersEvent(Tache $tache): Event
    {
        $event = new Event();

        // Titre avec emoji — identique au Java
        $emoji = $this->emojiStatut($tache->getStatut());
        $event->setSummary($emoji . ' [Ardhi] ' . $tache->getTitre());

        // Description enrichie — identique au Java
        $desc = '';
        if ($tache->getDescription()) {
            $desc .= $tache->getDescription() . "\n\n";
        }
        $desc .= "─── Ardhi ───\n";
        $desc .= 'Statut    : ' . $tache->getStatut() . "\n";
        $desc .= 'Priorité  : ' . $this->labelPrio($tache->getPriorite()) . "\n";
        $desc .= 'Catégorie : ' . ($tache->getCategorie() ?? '—') . "\n";
        if ($tache->getIdEmploye()) {
            $desc .= 'Employé # : ' . $tache->getIdEmploye() . "\n";
        }
        $desc .= 'ID tâche  : #' . $tache->getId();
        $event->setDescription($desc);

        // Dates journée entière — identique au Java
        $debut = $tache->getDateDebut() ?? new \DateTime('today');
        $fin   = $tache->getDateFin()   ?? $debut;

        // Google Calendar all-day : format YYYY-MM-DD, fin = lendemain
        $start = new EventDateTime();
        $start->setDate($debut->format('Y-m-d'));
        $start->setTimeZone('Africa/Tunis');

        $finPlusUn = (clone \DateTime::createFromInterface($fin))->modify('+1 day');
        $end = new EventDateTime();
        $end->setDate($finPlusUn->format('Y-m-d'));
        $end->setTimeZone('Africa/Tunis');

        $event->setStart($start);
        $event->setEnd($end);

        // Couleur selon priorité — identique au Java
        $colorMap = [
            4 => '11', // Tomate — Critique
            3 => '6',  // Tangerine — Haute
            2 => '1',  // Lavande — Moyenne
            1 => '2',  // Sauge — Basse
        ];
        if ($tache->getPriorite() && isset($colorMap[$tache->getPriorite()])) {
            $event->setColorId($colorMap[$tache->getPriorite()]);
        }

        return $event;
    }

    private function emojiStatut(?string $s): string
    {
        return match($s) {
            'En cours' => '🔵',
            'Terminé'  => '✅',
            'Validé'   => '✔',
            'Annulé'   => '❌',
            default    => '⏳',
        };
    }

    private function labelPrio(?int $p): string
    {
        return match($p) {
            4       => '🔴 Critique',
            3       => '🟠 Haute',
            2       => '🔵 Moyenne',
            1       => '🟢 Basse',
            default => '—',
        };
    }
}