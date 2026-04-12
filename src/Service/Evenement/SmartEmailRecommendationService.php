<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Evenement;
use App\Repository\Evenement\ParticipationRepository;
use App\Repository\Evenement\EvenementRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

/**
 * Sends personalised HTML re-engagement emails to at-risk participants.
 * Ported from the Java SmartEmailRecommendationService.
 */
class SmartEmailRecommendationService
{
    public function __construct(
        private ParticipationRepository $participationRepo,
        private EvenementRepository     $evenementRepo,
        private MailerInterface         $mailer,
        private LoggerInterface         $logger,
        private string                  $mailFrom // Autowired from services.yaml binding
    ) {}

    // ═══════════════════════════════════════════════════════════════════════
    // PUBLIC API
    // ═══════════════════════════════════════════════════════════════════════

    /**
     * Send a personalised re-engagement email to one risk profile.
     *
     * @param array $profile  A risk-profile array from InactiveParticipantDetectionService
     */
    public function envoyerRelancePersonnalisee(array $profile): bool
    {
        $prefs        = $this->analyserHistoriqueUtilisateur($profile['userId']);
        $recommandations = $this->recommanderEvenements($prefs);

        $sujet   = $this->genererSujetPersonnalise($profile, $prefs);
        $htmlBody = $this->genererEmailHTML($profile, $prefs, $recommandations);

        try {
            $email = (new Email())
                ->from(new Address($this->mailFrom, 'Équipe Ardhi 🌱'))
                ->to($profile['email'])
                ->subject($sujet)
                ->html($htmlBody);

            $this->mailer->send($email);
            $this->logger->info('Re-engagement email sent to {email}', ['email' => $profile['email']]);
            return true;

        } catch (\Throwable $e) {
            $this->logger->error('Failed to send email to {email}: {msg}', [
                'email' => $profile['email'],
                'msg'   => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Send re-engagement emails to all profiles that meet the score threshold.
     * Returns stats: ['urgentes', 'importantes', 'standard', 'total', 'echecs']
     */
    public function envoyerRelancesAutomatiques(array $profiles): array
    {
        $stats = ['urgentes' => 0, 'importantes' => 0, 'standard' => 0, 'total' => 0, 'echecs' => 0];

        foreach ($profiles as $profile) {
            $score = $profile['riskScore'];

            if ($score >= 80) {
                $ok = $this->envoyerRelancePersonnalisee($profile);
                $ok ? $stats['urgentes']++ : $stats['echecs']++;
            } elseif ($score >= 60) {
                $ok = $this->envoyerRelancePersonnalisee($profile);
                $ok ? $stats['importantes']++ : $stats['echecs']++;
            } elseif ($score >= 30) {
                $ok = $this->envoyerRelancePersonnalisee($profile);
                $ok ? $stats['standard']++ : $stats['echecs']++;
            }
        }

        $stats['total'] = $stats['urgentes'] + $stats['importantes'] + $stats['standard'];
        return $stats;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // EMAIL BUILDING
    // ═══════════════════════════════════════════════════════════════════════

    private function genererEmailHTML(array $profile, array $prefs, array $recommandations): string
    {
        $prenom = htmlspecialchars($profile['prenom'] ?? 'Participant');
        $html   = <<<HTML
<!DOCTYPE html>
<html><head><meta charset="UTF-8"></head>
<body style="font-family:Arial,sans-serif;line-height:1.6;color:#333;max-width:600px;margin:0 auto;padding:20px;">
HTML;

        // ── Header ────────────────────────────────────────────────────────
        $html .= <<<HTML
<div style="background:linear-gradient(135deg,#667A3F,#8BC34A);color:white;padding:30px;text-align:center;border-radius:10px 10px 0 0;">
  <h1 style="margin:0;font-size:28px;">🌱 ARDHI</h1>
  <p style="margin:10px 0 0;font-size:14px;">Plateforme Agricole</p>
</div>
<div style="background:white;padding:30px;border:1px solid #e0e0e0;">
  <p style="font-size:18px;margin-bottom:20px;">Bonjour <strong>{$prenom}</strong>,</p>
HTML;

        // ── History block ─────────────────────────────────────────────────
        if ($prefs['nombreParticipationsPassees'] > 0) {
            $n    = $prefs['nombreParticipationsPassees'];
            $plur = $n > 1 ? 's' : '';
            $html .= <<<HTML
<div style="background:#F8F9FA;padding:20px;border-radius:8px;margin:20px 0;">
  <h3 style="color:#667A3F;margin-top:0;">📊 Votre historique</h3>
  <p>✓ Vous avez participé à <strong>{$n} événement{$plur}</strong><br>
     ✓ Merci de votre confiance et fidélité!</p>
</div>
HTML;
        }

        // ── Reason block ──────────────────────────────────────────────────
        $html .= '<div style="background:#E8F5E9;padding:20px;border-left:4px solid #4CAF50;margin:20px 0;">';
        $html .= '<h3 style="margin-top:0;color:#2E7D32;">💬 Pourquoi cet email ?</h3>';

        if ($profile['riskScore'] >= 80) {
            $html .= '<p>🎯 Nous avons remarqué votre absence récente<br>🎁 <strong>OFFRE SPÉCIALE</strong> : Inscription GRATUITE à votre prochain événement!</p>';
        } elseif ($profile['riskScore'] >= 60) {
            $html .= '<p>📢 Nous travaillons à améliorer nos événements<br>💡 Votre avis compte énormément pour nous<br>📧 Répondez à cet email pour nous dire ce qui vous intéresserait</p>';
        } else {
            $html .= '<p>🌟 Nous avons sélectionné des événements spécialement pour VOUS!</p>';
        }
        $html .= '</div>';

        // ── Recommended events ────────────────────────────────────────────
        if (!empty($recommandations)) {
            $typesLabel = implode(' et ', array_map([$this, 'traduireType'], $prefs['typesPreferences']));
            $html .= '<div style="margin:30px 0;">';
            $html .= '<h2 style="color:#667A3F;border-bottom:3px solid #8BC34A;padding-bottom:10px;">🎯 Vos événements recommandés</h2>';
            if ($typesLabel) {
                $html .= "<p style='color:#666;margin-bottom:25px;'>Basé sur votre intérêt pour : <strong>{$typesLabel}</strong></p>";
            }

            foreach ($recommandations as $i => $evt) {
                $emoji = match ($evt->getType()) {
                    'FOIRE'      => '🏪',
                    'FORMATION'  => '📚',
                    'CONFERENCE' => '🎤',
                    'ATELIER'    => '🔧',
                    default      => '🌾',
                };
                $titre = htmlspecialchars($evt->getTitre());
                $lieu  = htmlspecialchars($evt->getLieu());
                $date  = $evt->getDateDebut() ? $evt->getDateDebut()->format('d/m/Y') : 'À confirmer';
                $places = $evt->getNombrePlacesMax();
                $num   = $i + 1;

                $html .= <<<HTML
<div style="background:#fff;border:2px solid #E0E0E0;border-radius:10px;padding:20px;margin:15px 0;box-shadow:0 2px 4px rgba(0,0,0,.1);">
  <h3 style="color:#667A3F;margin-top:0;border-bottom:2px solid #8BC34A;padding-bottom:10px;">{$emoji} Événement #{$num}</h3>
  <p style="margin:15px 0;"><strong>📌 TITRE :</strong> {$titre}</p>
  <p style="margin:15px 0;"><strong>📍 LIEU :</strong> {$lieu}</p>
  <p style="margin:15px 0;"><strong>📅 DATE :</strong> {$date}</p>
  <p style="margin:15px 0;"><strong>👥 PLACES :</strong> {$places} places disponibles</p>
</div>
HTML;
            }

            $html .= '<p style="text-align:center;margin-top:25px;"><a href="http://www.ardhi-platform.tn" style="display:inline-block;background:linear-gradient(135deg,#667A3F,#8BC34A);color:white;padding:15px 40px;text-decoration:none;border-radius:25px;font-weight:bold;">🔗 Voir tous les événements</a></p>';
            $html .= '</div>';

        } else {
            $html .= <<<HTML
<div style="background:#FFF3CD;border:2px solid #FFC107;border-radius:8px;padding:20px;margin:20px 0;">
  <h3 style="color:#856404;margin-top:0;">⚠️ Aucun événement prévu actuellement</h3>
  <p>📬 Nous vous informerons dès qu'un nouvel événement correspondant à vos intérêts sera organisé.</p>
</div>
HTML;
        }

        // ── CTA ───────────────────────────────────────────────────────────
        $html .= '<div style="background:#E3F2FD;padding:20px;border-radius:8px;margin:25px 0;">';
        $html .= '<h3 style="color:#1565C0;margin-top:0;">💡 Comment s\'inscrire ?</h3><ol style="margin:15px 0;">';
        if ($profile['riskScore'] >= 60) {
            $html .= '<li>📧 Répondez directement à cet email</li><li>📱 Appelez-nous au : +216 XX XXX XXX</li><li>💻 Connectez-vous sur www.ardhi-platform.tn</li>';
        } else {
            $html .= '<li>Connectez-vous à votre compte Ardhi</li><li>Consultez la section « Événements »</li><li>Cliquez sur « S\'inscrire »</li><li>Confirmez votre participation</li>';
        }
        $html .= '</ol></div></div>'; // close CTA + content

        // ── Footer ────────────────────────────────────────────────────────
        $regionPref = !empty($prefs['lieuxPreferences']) ? htmlspecialchars($prefs['lieuxPreferences'][0]) : null;
        $regionNote = $regionPref
            ? "<p style='margin:15px 0;font-size:12px;color:#888;'>💡 Nous organisons régulièrement des événements à {$regionPref}, votre région préférée!</p>"
            : '';

        $html .= <<<HTML
<div style="background:#F8F9FA;padding:25px;text-align:center;border-radius:0 0 10px 10px;border:1px solid #e0e0e0;border-top:none;">
  <p style="margin:10px 0;font-size:14px;">Cordialement,<br><strong>🌱 L'équipe Ardhi</strong><br>Votre partenaire pour l'agriculture moderne</p>
  <p style="margin:15px 0;font-size:13px;color:#666;">📧 support@ardhi-platform.tn<br>🌐 www.ardhi-platform.tn<br>📱 +216 XX XXX XXX</p>
  {$regionNote}
  <p style="margin-top:20px;font-size:12px;color:#999;">Merci de faire partie de notre communauté! 🌾</p>
</div>
</body></html>
HTML;

        return $html;
    }

    // ═══════════════════════════════════════════════════════════════════════
    // PREFERENCE ANALYSIS & EVENT RECOMMENDATION
    // ═══════════════════════════════════════════════════════════════════════

    private function analyserHistoriqueUtilisateur(int $userId): array
    {
        $participations = array_filter(
            $this->participationRepo->findAll(),
            fn($p) => $p->getUtilisateur()?->getId() === $userId
        );

        $typesCount = [];
        $lieuxCount = [];

        foreach ($participations as $p) {
            $evt = $p->getEvenement();
            if (!$evt) continue;

            $type = $evt->getType();
            $lieu = $evt->getLieu();

            $typesCount[$type] = ($typesCount[$type] ?? 0) + 1;
            $lieuxCount[$lieu] = ($lieuxCount[$lieu] ?? 0) + 1;

            // Attending earns double weight
            if ($p->getStatut() === 'PRESENT') {
                $typesCount[$type] += 2;
            }
        }

        arsort($typesCount);
        arsort($lieuxCount);

        return [
            'typesPreferences'          => array_slice(array_keys($typesCount), 0, 2),
            'lieuxPreferences'          => array_slice(array_keys($lieuxCount), 0, 2),
            'nombreParticipationsPassees' => count($participations),
        ];
    }

    /**
     * @return Evenement[]
     */
    private function recommanderEvenements(array $prefs): array
    {
        $aVenir = $this->evenementRepo->findByStatut('A_VENIR');
        if (empty($aVenir)) return [];

        $scores = [];
        $now    = new \DateTime();

        foreach ($aVenir as $evt) {
            $score = 0.0;

            // Type preference
            if (in_array($evt->getType(), $prefs['typesPreferences'], true)) {
                $score += 50.0;
                if (($prefs['typesPreferences'][0] ?? null) === $evt->getType()) {
                    $score += 20.0;
                }
            }

            // Location preference
            foreach ($prefs['lieuxPreferences'] as $lieuPref) {
                if (str_contains(strtolower($evt->getLieu()), strtolower($lieuPref))) {
                    $score += 30.0;
                    break;
                }
            }

            // Proximity in time
            if ($evt->getDateDebut()) {
                $days = (int) $now->diff($evt->getDateDebut())->days;
                if ($days > 0 && $days <= 30)  $score += 20.0;
                elseif ($days > 30 && $days <= 60) $score += 10.0;
            }

            // Penalise tiny events
            if ($evt->getNombrePlacesMax() < 20) $score -= 10.0;

            $scores[] = ['evt' => $evt, 'score' => $score];
        }

        usort($scores, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_map(fn($s) => $s['evt'], array_slice($scores, 0, 3));
    }

    private function genererSujetPersonnalise(array $profile, array $prefs): string
    {
        $prenom = $profile['prenom'] ?? 'Participant';

        if ($profile['riskScore'] >= 80) {
            return "🎁 {$prenom}, une offre spéciale vous attend!";
        }
        if ($profile['riskScore'] >= 60) {
            return "👋 {$prenom}, nous aimerions votre avis";
        }
        if (!empty($prefs['typesPreferences'])) {
            $type = $this->traduireType($prefs['typesPreferences'][0]);
            return "🌾 {$prenom}, nouveaux {$type} pour vous!";
        }
        return "🌱 {$prenom}, événements sélectionnés pour vous";
    }

    private function traduireType(string $type): string
    {
        return match ($type) {
            'FOIRE'      => 'foires agricoles',
            'FORMATION'  => 'formations',
            'CONFERENCE' => 'conférences',
            'ATELIER'    => 'ateliers pratiques',
            default      => 'événements',
        };
    }
}
