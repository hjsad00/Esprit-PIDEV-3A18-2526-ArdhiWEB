<?php

namespace App\Service\MaterielEtMaintenance;

use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\UserAndDiag\User;

class GoogleCalendarService
{
    private Client $client;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
        private EntityManagerInterface $em
    ) {
        $this->client = new Client();
        $credentialsPath = $this->projectDir . '/config/google_credentials.json';
        
        if (file_exists($credentialsPath)) {
            $this->client->setAuthConfig($credentialsPath);
        }
        
        $this->client->addScope(Calendar::CALENDAR_EVENTS);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    /**
     * S'assure que le token d'accès est valide (le rafraîchit s'il est expiré).
     * Retourne le client configuré, ou null si l'authentification échoue.
     */
    public function authenticateUserClient(User $user): ?Client
    {
        $accessToken = $user->getGoogleAccessToken();
        if (!$accessToken) {
            return null;
        }

        $this->client->setAccessToken($accessToken);

        if ($this->client->isAccessTokenExpired()) {
            $refreshToken = $user->getGoogleRefreshToken();
            if (!$refreshToken) {
                return null;
            }

            $newToken = $this->client->fetchAccessTokenWithRefreshToken($refreshToken);
            
            if (isset($newToken['error'])) {
                return null;
            }

            $user->setGoogleAccessToken($newToken['access_token']);
            if (isset($newToken['refresh_token'])) {
                $user->setGoogleRefreshToken($newToken['refresh_token']);
            }
            $this->em->flush();
            $this->client->setAccessToken($newToken['access_token']);
        }

        return $this->client;
    }

    public function createMaintenanceEvent(User $user, string $title, string $description, \DateTimeInterface $date): ?array
    {
        $client = $this->authenticateUserClient($user);
        if (!$client) {
            return null;
        }

        $calendarService = new Calendar($client);
        
        // Planning l'événement de 9h à 12h par défaut en heure GMT+1 (Tunis)
        $event = new Event([
            'summary' => '🚜 Maintenance: ' . $title,
            'description' => $description,
            'colorId' => '9', // Blueberry color
            'start' => [
                'dateTime' => $date->format('Y-m-d\TH:i:s'),
                'timeZone' => 'Africa/Tunis',
            ],
            'end' => [
                'dateTime' => (clone $date)->modify('+3 hours')->format('Y-m-d\TH:i:s'),
                'timeZone' => 'Africa/Tunis',
            ],
            'reminders' => [
                'useDefault' => false,
                'overrides' => [
                    ['method' => 'popup', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 24 * 60], // 1 day before
                ],
            ]
        ]);

        try {
            $createdEvent = $calendarService->events->insert('primary', $event);
            return [
                'id' => $createdEvent->getId(),
                'link' => $createdEvent->getHtmlLink()
            ];
        } catch (\Exception $e) {
            return null;
        }
    }

    public function updateMaintenanceEvent(User $user, string $eventId, string $title, string $description, \DateTimeInterface $date): ?array
    {
        $client = $this->authenticateUserClient($user);
        if (!$client) {
            return null;
        }

        $calendarService = new Calendar($client);

        try {
            // Récupérer l'événement existant
            $event = $calendarService->events->get('primary', $eventId);
            
            // Mettre à jour les informations
            $event->setSummary('🚜 Maintenance: ' . $title);
            $event->setDescription($description);

            $start = new \Google\Service\Calendar\EventDateTime();
            $start->setDateTime($date->format('Y-m-d\TH:i:s'));
            $start->setTimeZone('Africa/Tunis');
            $event->setStart($start);

            $end = new \Google\Service\Calendar\EventDateTime();
            $end->setDateTime((clone $date)->modify('+3 hours')->format('Y-m-d\TH:i:s'));
            $end->setTimeZone('Africa/Tunis');
            $event->setEnd($end);

            $updatedEvent = $calendarService->events->update('primary', $eventId, $event);
            return [
                'id' => $updatedEvent->getId(),
                'link' => $updatedEvent->getHtmlLink()
            ];
        } catch (\Exception $e) {
            // Si l'événement n'existe pas ou erreur, on le recrée
            return $this->createMaintenanceEvent($user, $title, $description, $date);
        }
    }

    public function deleteMaintenanceEvent(User $user, string $eventId): bool
    {
        $client = $this->authenticateUserClient($user);
        if (!$client) {
            return false;
        }

        $calendarService = new Calendar($client);

        try {
            $calendarService->events->delete('primary', $eventId);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
