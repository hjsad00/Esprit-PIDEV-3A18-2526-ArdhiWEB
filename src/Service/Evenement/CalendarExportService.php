<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Evenement;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\EventReminder;
use Google\Service\Calendar\EventReminders;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class CalendarExportService
{
    private const CLIENT_ID = '912344954797-lcmfd0q95p4enbp2lhtjllhs43563oup.apps.googleusercontent.com';
    private const CLIENT_SECRET = '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';
    private const REDIRECT_URI = 'http://localhost:8000/evenement/calendar/callback';
    private const SCOPES = [Calendar::CALENDAR_EVENTS];
    private const APP_NAME = 'ARDHI - Module Evenements';

    public function __construct(
        private LoggerInterface $logger,
        private string $projectDir,
        private RequestStack $requestStack
    ) {}

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

    public function handleOAuthCallback(string $code, string $userEmail): bool
    {
        try {
            $client = $this->buildClient();
            $token = $client->fetchAccessTokenWithAuthCode($code);

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
     * @return array<string, mixed>
     */
    public function ajouterAuCalendrier(Evenement $evenement, string $userEmail): array
    {
        if ($this->hasValidToken($userEmail)) {
            return $this->ajouterViaAPI($evenement, $userEmail);
        }

        $this->logger->info('No Google token for {email} - generating .ics fallback', ['email' => $userEmail]);

        return $this->ajouterViaICS($evenement);
    }

    public function isConnected(string $userEmail): bool
    {
        return $this->hasValidToken($userEmail);
    }

    public function genererICS(Evenement $evenement): string
    {
        return $this->construireContenuICS($evenement);
    }

    /**
     * @return array<string, mixed>
     */
    private function ajouterViaAPI(Evenement $evenement, string $userEmail): array
    {
        try {
            $client = $this->buildClient();
            $token = $this->loadToken($userEmail);
            if ($token === null) {
                return $this->ajouterViaICS($evenement);
            }

            $client->setAccessToken($token);

            if ($client->isAccessTokenExpired()) {
                $refreshToken = $client->getRefreshToken();
                if (is_string($refreshToken) && $refreshToken !== '') {
                    $newToken = $client->fetchAccessTokenWithRefreshToken($refreshToken);
                    $this->saveToken($userEmail, $newToken);
                    $client->setAccessToken($newToken);
                } else {
                    $this->deleteToken($userEmail);

                    return $this->ajouterViaICS($evenement);
                }
            }

            $service = new Calendar($client);
            $event = $this->buildGoogleEvent($evenement);
            $created = $service->events->insert('primary', $event);

            return [
                'success' => true,
                'mode' => 'api',
                'link' => $created->getHtmlLink(),
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Google Calendar API error: {msg}', ['msg' => $e->getMessage()]);

            return $this->ajouterViaICS($evenement);
        }
    }

    private function buildGoogleEvent(Evenement $evenement): Event
    {
        $event = new Event();
        $event->setSummary('Event ' . ($evenement->getTitre() ?? 'ARDHI'));
        $event->setLocation($evenement->getLieu() ?? '');
        $event->setDescription($this->construireDescriptionAPI($evenement));
        $event->setColorId('10');

        $startDate = $this->createTimedDate($this->requireDateDebut($evenement), 9, 0);
        $endDate = $this->createTimedDate($this->requireDateFin($evenement), 17, 0);

        $start = new EventDateTime();
        $start->setDateTime($startDate->format(\DateTime::RFC3339));
        $start->setTimeZone('Africa/Tunis');
        $event->setStart($start);

        $end = new EventDateTime();
        $end->setDateTime($endDate->format(\DateTime::RFC3339));
        $end->setTimeZone('Africa/Tunis');
        $event->setEnd($end);

        $emailReminder = new EventReminder();
        $emailReminder->setMethod('email');
        $emailReminder->setMinutes(24 * 60);

        $popupReminder = new EventReminder();
        $popupReminder->setMethod('popup');
        $popupReminder->setMinutes(60);

        $reminders = new EventReminders();
        $reminders->setUseDefault(false);
        $reminders->setOverrides([$emailReminder, $popupReminder]);
        $event->setReminders($reminders);

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function ajouterViaICS(Evenement $evenement): array
    {
        return [
            'success' => true,
            'mode' => 'ics',
            'icsContent' => $this->construireContenuICS($evenement),
            'filename' => 'event_' . ($evenement->getId() ?? 'new') . '_' . time() . '.ics',
        ];
    }

    private function construireContenuICS(Evenement $evenement): string
    {
        $dateDebut = $this->createTimedDate($this->requireDateDebut($evenement), 9, 0);
        $dateFin = $this->createTimedDate($this->requireDateFin($evenement), 17, 0);
        $titre = str_replace(["\n", "\r"], ' ', $evenement->getTitre() ?? '');
        $lieu = str_replace(["\n", "\r"], ' ', $evenement->getLieu() ?? '');

        return implode("\r\n", [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//ARDHI//Plateforme Agricole//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . bin2hex(random_bytes(16)) . '@ardhi.tn',
            'DTSTAMP:' . (new \DateTimeImmutable())->format('Ymd\THis'),
            'DTSTART:' . $dateDebut->format('Ymd\THis'),
            'DTEND:' . $dateFin->format('Ymd\THis'),
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
        $dateDebut = $evenement->getDateDebut();
        $dateFin = $evenement->getDateFin();

        return implode("\n", [
            'ARDHI Evenement Agricole',
            '',
            'Du ' . ($dateDebut ? $dateDebut->format('d/m/Y') : 'a confirmer') . ' au ' . ($dateFin ? $dateFin->format('d/m/Y') : 'a confirmer'),
            'Lieu: ' . ($evenement->getLieu() ?? 'A confirmer'),
            'Type: ' . ($evenement->getType() ?? 'Non renseigne'),
            'Organisateur: ' . ($evenement->getOrganisateur() ?? 'Non renseigne'),
            'Places: ' . ($evenement->getNombrePlacesMax() ?? 0),
            '',
            $evenement->getDescription() ?? '',
        ]);
    }

    private function tokenPath(string $userEmail): string
    {
        $dir = $this->projectDir . '/var/google_tokens/evenements';
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }

        return $dir . '/' . preg_replace('/[^a-z0-9]/i', '_', $userEmail) . '.json';
    }

    /**
     * @param array<string, mixed> $token
     */
    private function saveToken(string $userEmail, array $token): void
    {
        file_put_contents($this->tokenPath($userEmail), json_encode($token));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function loadToken(string $userEmail): ?array
    {
        $path = $this->tokenPath($userEmail);
        if (!file_exists($path)) {
            return null;
        }

        $json = file_get_contents($path);
        if ($json === false) {
            return null;
        }

        $decoded = json_decode($json, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function deleteToken(string $userEmail): void
    {
        $path = $this->tokenPath($userEmail);
        if (file_exists($path)) {
            unlink($path);
        }
    }

    private function hasValidToken(string $userEmail): bool
    {
        $token = $this->loadToken($userEmail);
        if ($token === null) {
            return false;
        }

        $client = $this->buildClient();
        $client->setAccessToken($token);

        return !$client->isAccessTokenExpired() || !empty($token['refresh_token']);
    }

    private function requireDateDebut(Evenement $evenement): \DateTimeInterface
    {
        $dateDebut = $evenement->getDateDebut();
        if ($dateDebut === null) {
            throw new \LogicException('La date de début est obligatoire pour exporter un événement.');
        }

        return $dateDebut;
    }

    private function requireDateFin(Evenement $evenement): \DateTimeInterface
    {
        $dateFin = $evenement->getDateFin();
        if ($dateFin === null) {
            throw new \LogicException('La date de fin est obligatoire pour exporter un événement.');
        }

        return $dateFin;
    }

    private function createTimedDate(\DateTimeInterface $date, int $hour, int $minute): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromInterface($date)->setTime($hour, $minute);
    }

    private function buildClient(): Client
    {
        $client = new Client();
        $client->setApplicationName(self::APP_NAME);
        $client->setClientId(self::CLIENT_ID);
        $client->setClientSecret(self::CLIENT_SECRET);
        $client->setRedirectUri(self::REDIRECT_URI);
        $client->setScopes(self::SCOPES);
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }
}
