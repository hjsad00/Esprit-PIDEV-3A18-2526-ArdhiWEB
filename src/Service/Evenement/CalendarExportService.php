<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Evenement;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\EventReminder;
use Google\Service\Calendar\Event\Reminders;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Exports events to Google Calendar (via OAuth2 API) with .ics fallback.
 * Ported from the Java CalendarExportService.
 *
 * Requires: composer require google/apiclient
 */
class CalendarExportService
{
    // ── Google OAuth2 credentials (your ardhi-evenements project) ────────────
    private const CLIENT_ID     = '912344954797-12s3iompalitbb3blhkhu17r3hrtcmqo.apps.googleusercontent.com';
    private const CLIENT_SECRET = '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';
    private const REDIRECT_URI  = 'http://localhost:8000/evenement/calendar/callback'; // adjust to your dev URL
    private const SCOPES        = [Calendar::CALENDAR_EVENTS];
    private const APP_NAME      = 'ARDHI - Module Événements';

    public function __construct(
        private LoggerInterface $logger,
        private string          $projectDir,
        private RequestStack    $requestStack
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Build the Google OAuth2 authorization URL to redirect the user to.
     * Store `state` in session to validate the callback.
     */
    public function getAuthorizationUrl(string $userEmail): string
    {
        $client = $this->buildClient();
        $client->setLoginHint($userEmail);

        $state = bin2hex(random_bytes(16));
        $session = $this->requestStack->getSession();
        $session->set('google_calendar_oauth_state', $state);
        $client->setState($state);

        return $client->createAuthUrl();
    }

    /**
     * Exchange an OAuth2 authorization code for an access token and persist it.
     * Call this from the OAuth callback controller action.
     *
     * @return bool  true on success
     */
    public function handleOAuthCallback(string $code, string $userEmail): bool
    {
        try {
            $client = $this->buildClient();
            $token  = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                $this->logger->error('Google OAuth token error: {e}', ['e' => $token['error']]);
                return false;
            }

            $this->saveToken($userEmail, $token);
            $this->logger->info('Google Calendar token saved for {email}', ['email' => $userEmail]);
            return true;

        } catch (\Throwable $e) {
            $this->logger->error('OAuth callback failed: {msg}', ['msg' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Add an event directly to the user's primary Google Calendar.
     * Falls back to generating a downloadable .ics file if not authenticated.
     *
     * @return array  ['success' => bool, 'mode' => 'api'|'ics', 'link'|'icsContent' => string]
     */
    public function ajouterAuCalendrier(Evenement $evenement, string $userEmail): array
    {
        if ($this->hasValidToken($userEmail)) {
            return $this->ajouterViaAPI($evenement, $userEmail);
        }

        $this->logger->info('No Google token for {email} — generating .ics fallback', ['email' => $userEmail]);
        return $this->ajouterViaICS($evenement);
    }

    /**
     * Returns true if the user already has a stored (non-expired) token.
     */
    public function isConnected(string $userEmail): bool
    {
        return $this->hasValidToken($userEmail);
    }

    /**
     * Generate and return an .ics string for the event (no Google auth needed).
     */
    public function genererICS(Evenement $evenement): string
    {
        return $this->construireContenuICS($evenement);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // GOOGLE CALENDAR API
    // ═══════════════════════════════════════════════════════════════════════

    private function ajouterViaAPI(Evenement $evenement, string $userEmail): array
    {
        try {
            $client          = $this->buildClient();
            $token           = $this->loadToken($userEmail);
            $client->setAccessToken($token);

            // Refresh if expired
            if ($client->isAccessTokenExpired()) {
                if ($client->getRefreshToken()) {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
                    $this->saveToken($userEmail, $newToken);
                    $client->setAccessToken($newToken);
                } else {
                    // Token expired and no refresh token → need re-auth
                    $this->deleteToken($userEmail);
                    return $this->ajouterViaICS($evenement);
                }
            }

            $service  = new Calendar($client);
            $event    = $this->buildGoogleEvent($evenement);
            $created  = $service->events->insert('primary', $event);

            $this->logger->info('Event added to Google Calendar: {link}', ['link' => $created->getHtmlLink()]);

            return [
                'success' => true,
                'mode'    => 'api',
                'link'    => $created->getHtmlLink(),
            ];

        } catch (\Throwable $e) {
            $this->logger->error('Google Calendar API error: {msg}', ['msg' => $e->getMessage()]);
            return $this->ajouterViaICS($evenement);
        }
    }

    private function buildGoogleEvent(Evenement $evenement): Event
    {
        $event = new Event();
        $event->setSummary('🎪 ' . $evenement->getTitre());
        $event->setLocation($evenement->getLieu());
        $event->setDescription($this->construireDescriptionAPI($evenement));
        $event->setColorId('10'); // green

        $tz         = 'Africa/Tunis';
        $dateDebut  = \DateTime::createFromFormat('Y-m-d', $evenement->getDateDebut()->format('Y-m-d'));
        $dateDebut->setTime(9, 0);
        $dateFin    = \DateTime::createFromFormat('Y-m-d', $evenement->getDateFin()->format('Y-m-d'));
        $dateFin->setTime(17, 0);

        $start = new EventDateTime();
        $start->setDateTime($dateDebut->format(\DateTime::RFC3339));
        $start->setTimeZone($tz);
        $event->setStart($start);

        $end = new EventDateTime();
        $end->setDateTime($dateFin->format(\DateTime::RFC3339));
        $end->setTimeZone($tz);
        $event->setEnd($end);

        // Reminders: 24h email + 1h popup
        $emailReminder = new EventReminder();
        $emailReminder->setMethod('email');
        $emailReminder->setMinutes(24 * 60);

        $popupReminder = new EventReminder();
        $popupReminder->setMethod('popup');
        $popupReminder->setMinutes(60);

        $reminders = new Reminders();
        $reminders->setUseDefault(false);
        $reminders->setOverrides([$emailReminder, $popupReminder]);
        $event->setReminders($reminders);

        return $event;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // ICS FALLBACK
    // ═══════════════════════════════════════════════════════════════════════

    private function ajouterViaICS(Evenement $evenement): array
    {
        return [
            'success'    => true,
            'mode'       => 'ics',
            'icsContent' => $this->construireContenuICS($evenement),
            'filename'   => 'event_' . $evenement->getId() . '_' . time() . '.ics',
        ];
    }

    private function construireContenuICS(Evenement $evenement): string
    {
        $dateDebut = \DateTime::createFromFormat('Y-m-d', $evenement->getDateDebut()->format('Y-m-d'));
        $dateDebut->setTime(9, 0);
        $dateFin = \DateTime::createFromFormat('Y-m-d', $evenement->getDateFin()->format('Y-m-d'));
        $dateFin->setTime(17, 0);

        $uid     = bin2hex(random_bytes(16)) . '@ardhi.tn';
        $dtstamp = (new \DateTime())->format('Ymd\THis');
        $titre   = str_replace(["\n", "\r"], ' ', $evenement->getTitre());
        $lieu    = str_replace(["\n", "\r"], ' ', $evenement->getLieu());

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ARDHI//Plateforme Agricole//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $dtstamp,
            'DTSTART:' . $dateDebut->format('Ymd\THis'),
            'DTEND:'   . $dateFin->format('Ymd\THis'),
            'SUMMARY:' . $titre,
            'LOCATION:' . $lieu,
            'STATUS:CONFIRMED',
            'BEGIN:VALARM',
            'TRIGGER:-PT24H',
            'ACTION:DISPLAY',
            'DESCRIPTION:Rappel: ' . $titre . ' demain',
            'END:VALARM',
            'END:VEVENT',
            'END:VCALENDAR',
        ]) . "\r\n";
    }

    private function construireDescriptionAPI(Evenement $evenement): string
    {
        $fmt = 'd/m/Y';
        return implode("\n", [
            '🎪 ÉVÉNEMENT AGRICOLE ARDHI',
            '',
            '📅 Du ' . $evenement->getDateDebut()->format($fmt) . ' au ' . $evenement->getDateFin()->format($fmt),
            '📍 Lieu: ' . $evenement->getLieu(),
            '🏷️ Type: ' . $evenement->getType(),
            '👤 Organisateur: ' . $evenement->getOrganisateur(),
            '👥 Places: ' . $evenement->getNombrePlacesMax(),
            '',
            $evenement->getDescription() ? '📝 ' . $evenement->getDescription() : '',
            '',
            '🌾 Plateforme ARDHI - Événements Agricoles',
        ]);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // TOKEN PERSISTENCE
    // ═══════════════════════════════════════════════════════════════════════

    private function tokenPath(string $userEmail): string
    {
        $dir = $this->projectDir . '/var/google_tokens/evenements';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
        return $dir . '/' . preg_replace('/[^a-z0-9]/i', '_', $userEmail) . '.json';
    }

    private function saveToken(string $userEmail, array $token): void
    {
        file_put_contents($this->tokenPath($userEmail), json_encode($token));
    }

    private function loadToken(string $userEmail): ?array
    {
        $path = $this->tokenPath($userEmail);
        if (!file_exists($path)) return null;
        return json_decode(file_get_contents($path), true);
    }

    private function deleteToken(string $userEmail): void
    {
        $path = $this->tokenPath($userEmail);
        if (file_exists($path)) unlink($path);
    }

    private function hasValidToken(string $userEmail): bool
    {
        $token = $this->loadToken($userEmail);
        if (!$token) return false;

        $client = $this->buildClient();
        $client->setAccessToken($token);

        // Valid if not expired, OR if we have a refresh token to renew it
        return !$client->isAccessTokenExpired() || !empty($token['refresh_token']);
    }

    // ═══════════════════════════════════════════════════════════════════════
    // GOOGLE CLIENT BUILDER
    // ═══════════════════════════════════════════════════════════════════════

    private function buildClient(): Client
    {
        $client = new Client();
        $client->setApplicationName(self::APP_NAME);
        $client->setClientId(self::CLIENT_ID);
        $client->setClientSecret(self::CLIENT_SECRET);
        $client->setRedirectUri(self::REDIRECT_URI);
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');
        $client->setPrompt('consent');   // force refresh_token on first auth

        return $client;
    }
}
