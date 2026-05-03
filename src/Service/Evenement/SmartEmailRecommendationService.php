<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Evenement;
use App\Entity\Evenement\Participation;
use App\Repository\Evenement\EvenementRepository;
use App\Repository\Evenement\ParticipationRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class SmartEmailRecommendationService
{
    public function __construct(
        private ParticipationRepository $participationRepo,
        private EvenementRepository $evenementRepo,
        private MailerInterface $mailer,
        private LoggerInterface $logger,
        private string $mailFrom
    ) {}

    /**
     * @param array<string, mixed> $profile
     */
    public function envoyerRelancePersonnalisee(array $profile): bool
    {
        $userId = $profile['userId'] ?? null;
        $emailAddress = $profile['email'] ?? null;

        if (!is_int($userId) || !is_string($emailAddress) || $emailAddress === '') {
            return false;
        }

        $prefs = $this->analyserHistoriqueUtilisateur($userId);
        $recommandations = $this->recommanderEvenements($prefs);

        try {
            $email = (new Email())
                ->from(new Address($this->mailFrom, 'Equipe Ardhi'))
                ->to($emailAddress)
                ->subject($this->genererSujetPersonnalise($profile, $prefs))
                ->html($this->genererEmailHTML($profile, $prefs, $recommandations));

            $this->mailer->send($email);
            $this->logger->info('Re-engagement email sent to {email}', ['email' => $emailAddress]);

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email to {email}: {msg}', [
                'email' => $emailAddress,
                'msg' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @param list<array<string, mixed>> $profiles
     * @return array{urgentes: int, importantes: int, standard: int, total: int, echecs: int}
     */
    public function envoyerRelancesAutomatiques(array $profiles): array
    {
        $stats = ['urgentes' => 0, 'importantes' => 0, 'standard' => 0, 'total' => 0, 'echecs' => 0];

        foreach ($profiles as $profile) {
            $score = $profile['riskScore'] ?? 0;
            if (!is_numeric($score) || $score < 30) {
                continue;
            }

            $ok = $this->envoyerRelancePersonnalisee($profile);
            if ($score >= 80) {
                $ok ? $stats['urgentes']++ : $stats['echecs']++;
            } elseif ($score >= 60) {
                $ok ? $stats['importantes']++ : $stats['echecs']++;
            } else {
                $ok ? $stats['standard']++ : $stats['echecs']++;
            }
        }

        $stats['total'] = $stats['urgentes'] + $stats['importantes'] + $stats['standard'];

        return $stats;
    }

    /**
     * @param array<string, mixed> $profile
     * @param array{typesPreferences: list<string>, lieuxPreferences: list<string>, nombreParticipationsPassees: int} $prefs
     * @param list<Evenement> $recommandations
     */
    private function genererEmailHTML(array $profile, array $prefs, array $recommandations): string
    {
        $prenom = htmlspecialchars(is_string($profile['prenom'] ?? null) ? $profile['prenom'] : 'Participant');
        $items = '';

        foreach ($recommandations as $evt) {
            $titre = htmlspecialchars($evt->getTitre() ?? 'Événement');
            $lieu = htmlspecialchars($evt->getLieu() ?? 'À confirmer');
            $date = $evt->getDateDebut()?->format('d/m/Y') ?? 'À confirmer';
            $items .= "<li><strong>{$titre}</strong> - {$lieu} - {$date}</li>";
        }

        if ($items === '') {
            $items = '<li>Aucune recommandation disponible pour le moment.</li>';
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<body>
<p>Bonjour {$prenom},</p>
<p>Nous avons sélectionné quelques événements susceptibles de vous intéresser.</p>
<ul>{$items}</ul>
</body>
</html>
HTML;
    }

    /**
     * @return array{typesPreferences: list<string>, lieuxPreferences: list<string>, nombreParticipationsPassees: int}
     */
    private function analyserHistoriqueUtilisateur(int $userId): array
    {
        /** @var list<Participation> $participations */
        $participations = array_values(array_filter(
            $this->participationRepo->findAll(),
            static fn (mixed $participation): bool => $participation instanceof Participation
                && $participation->getUtilisateur()?->getId() === $userId
        ));

        $typesCount = [];
        $lieuxCount = [];

        foreach ($participations as $participation) {
            $evenement = $participation->getEvenement();
            if (!$evenement instanceof Evenement) {
                continue;
            }

            $type = $evenement->getType();
            $lieu = $evenement->getLieu();
            if ($type === null || $lieu === null || $lieu === '') {
                continue;
            }

            $typesCount[$type] = ($typesCount[$type] ?? 0) + 1;
            $lieuxCount[$lieu] = ($lieuxCount[$lieu] ?? 0) + 1;

            if ($participation->getStatut() === 'PRESENT') {
                $typesCount[$type] += 2;
            }
        }

        arsort($typesCount);
        arsort($lieuxCount);

        return [
            'typesPreferences' => array_slice(array_keys($typesCount), 0, 2),
            'lieuxPreferences' => array_slice(array_keys($lieuxCount), 0, 2),
            'nombreParticipationsPassees' => count($participations),
        ];
    }

    /**
     * @param array{typesPreferences: list<string>, lieuxPreferences: list<string>, nombreParticipationsPassees: int} $prefs
     * @return list<Evenement>
     */
    private function recommanderEvenements(array $prefs): array
    {
        $evenements = $this->evenementRepo->findByStatut('A_VENIR');
        $scores = [];
        $now = new \DateTimeImmutable();

        foreach ($evenements as $evenement) {
            $score = 0.0;

            if (in_array($evenement->getType(), $prefs['typesPreferences'], true)) {
                $score += 50.0;
            }

            foreach ($prefs['lieuxPreferences'] as $lieuPref) {
                if (str_contains(strtolower($evenement->getLieu() ?? ''), strtolower($lieuPref))) {
                    $score += 30.0;
                    break;
                }
            }

            $dateDebut = $evenement->getDateDebut();
            if ($dateDebut !== null) {
                $days = (int) $now->diff(\DateTimeImmutable::createFromInterface($dateDebut))->days;
                if ($days > 0 && $days <= 30) {
                    $score += 20.0;
                }
            }

            $scores[] = ['evt' => $evenement, 'score' => $score];
        }

        usort($scores, static fn (array $left, array $right): int => $right['score'] <=> $left['score']);

        return array_map(
            static fn (array $row): Evenement => $row['evt'],
            array_slice($scores, 0, 3)
        );
    }

    /**
     * @param array<string, mixed> $profile
     * @param array{typesPreferences: list<string>, lieuxPreferences: list<string>, nombreParticipationsPassees: int} $prefs
     */
    private function genererSujetPersonnalise(array $profile, array $prefs): string
    {
        $prenom = is_string($profile['prenom'] ?? null) ? $profile['prenom'] : 'Participant';
        $score = $profile['riskScore'] ?? 0;

        if (is_numeric($score) && $score >= 80) {
            return "{$prenom}, une offre spéciale vous attend";
        }

        if (!empty($prefs['typesPreferences'])) {
            return "{$prenom}, nouveaux événements pour vous";
        }

        return "{$prenom}, nous avons pensé à vous";
    }
}
