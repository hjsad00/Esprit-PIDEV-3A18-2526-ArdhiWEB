<?php

namespace App\Service\Evenement;

use App\Entity\Evenement\Evenement;
use App\Repository\Evenement\EvenementRepository;
use App\Repository\Evenement\ParticipationRepository;

/**
 * Predicts expected attendance for an event using a weighted scoring model.
 * Ported from the Java ParticipationPredictionService.
 */
class ParticipationPredictionService
{
    // Factor weights (must sum to 1.0)
    private const POIDS_TYPE       = 0.30;
    private const POIDS_SAISON     = 0.25;
    private const POIDS_LIEU       = 0.20;
    private const POIDS_HISTORIQUE = 0.25;

    public function __construct(
        private EvenementRepository    $evenementRepo,
        private ParticipationRepository $participationRepo
    ) {}

    /**
     * Returns a prediction result array:
     * [
     *   'participantsPredits' => int,
     *   'confiance'           => float,          // 0–1
     *   'confianceTexte'      => string,
     *   'facteurs'            => ['type','saison','lieu','historique' => float],
     *   'recommandations'     => ['materiel','restauration','espace','staff' => string],
     * ]
     *
     * @return array<string, mixed>
     */
    public function predireParticipation(Evenement $evenement): array
    {
        $type = $evenement->getType() ?? '';
        $lieu = $evenement->getLieu() ?? '';
        $nombrePlacesMax = $evenement->getNombrePlacesMax() ?? 10;

        $historiqueStats = $this->analyserHistorique($type);

        $scoreType       = $this->getScoreType($type);
        $scoreSaison     = $this->getScoreSaison($evenement->getDateDebut());
        $scoreLieu       = $this->getScoreLieu($lieu);
        $scoreHistorique = $historiqueStats['moyenne'];

        $prediction = ($scoreType       * self::POIDS_TYPE)
                    + ($scoreSaison     * self::POIDS_SAISON)
                    + ($scoreLieu       * self::POIDS_LIEU)
                    + ($scoreHistorique * self::POIDS_HISTORIQUE);

        $prediction = $this->ajusterSelonContenu($prediction, $evenement);
        $prediction = $this->ajusterAvecTendances($prediction, $evenement);

        $participantsPredits = (int) round($prediction);
        $participantsPredits = max(10, min($participantsPredits, $nombrePlacesMax));

        $confiance = $this->calculerConfiance($historiqueStats);

        return [
            'participantsPredits' => $participantsPredits,
            'confiance'           => $confiance,
            'confianceTexte'      => $this->confianceTexte($confiance),
            'facteurs'            => [
                'type'       => $scoreType,
                'saison'     => $scoreSaison,
                'lieu'       => $scoreLieu,
                'historique' => $scoreHistorique,
            ],
            'recommandations' => $this->genererRecommandations($participantsPredits, $evenement),
        ];
    }

    // ── Scoring helpers ──────────────────────────────────────────────────────

    /**
     * @return array{moyenne: float, max: float, min: float}
     */
    private function analyserHistorique(string $type): array
    {
        $termines = array_filter(
            $this->evenementRepo->findByStatut('TERMINE'),
            fn($e) => $e->getType() === $type
        );

        if (empty($termines)) {
            return ['moyenne' => 50.0, 'max' => 100.0, 'min' => 20.0];
        }

        $counts = [];
        foreach ($termines as $evt) {
            $counts[] = $this->participationRepo->countByStatut($evt, 'PRESENT');
        }

        return [
            'moyenne' => array_sum($counts) / count($counts),
            'max'     => (float) max($counts),
            'min'     => (float) min($counts),
        ];
    }

    private function getScoreType(string $type): float
    {
        return match ($type) {
            'FOIRE'      => 80.0,
            'FORMATION'  => 60.0,
            'CONFERENCE' => 50.0,
            'ATELIER'    => 40.0,
            default      => 50.0,
        };
    }

    private function getScoreSaison(?\DateTimeInterface $date): float
    {
        if (!$date) {
            return 50.0;
        }

        return match ((int) $date->format('n')) {
            3, 4, 5   => 90.0,   // Spring – high season
            9, 10, 11 => 85.0,   // Autumn – harvests
            6, 7      => 60.0,   // Summer – slower
            default   => 55.0,   // Winter
        };
    }

    private function getScoreLieu(string $lieu): float
    {
        $l = strtolower($lieu);

        if (str_contains($l, 'bizerte') || str_contains($l, 'béja'))    return 85.0;
        if (str_contains($l, 'tunis')   || str_contains($l, 'ariana'))  return 80.0;
        if (str_contains($l, 'sousse')  || str_contains($l, 'monastir')) return 75.0;
        if (str_contains($l, 'sfax')    || str_contains($l, 'kairouan')) return 75.0;
        if (str_contains($l, 'nabeul')  || str_contains($l, 'zaghouan')) return 70.0;

        return 60.0;
    }

    private function ajusterSelonContenu(float $prediction, Evenement $evenement): float
    {
        $titre       = strtolower($evenement->getTitre() ?? '');
        $description = strtolower($evenement->getDescription() ?? '');

        if (strlen($titre) < 10 || preg_match('/[a-z]{20,}/', $titre)) {
            $prediction *= 0.3;
        }

        if (strlen($description) < 50) {
            $prediction *= 0.5;
        }

        $keywords = ['formation', 'expert', 'certifié', 'officiel', 'ministère', 'national', 'agricole', 'technique'];
        foreach ($keywords as $kw) {
            if (str_contains($titre, $kw) || str_contains($description, $kw)) {
                $prediction *= 1.2;
                break;
            }
        }

        return $prediction;
    }

    private function ajusterAvecTendances(float $prediction, Evenement $evenement): float
    {
        $dateDebut = $evenement->getDateDebut();
        if ($dateDebut) {
            $joursAvant = (new \DateTime())->diff($dateDebut)->days;
            if ($joursAvant < 14) {
                $prediction *= 0.9;
            }
        }

        if ($evenement->getNombrePlacesMax() > 200) {
            $prediction *= 0.85;
        }

        return $prediction;
    }

    /**
     * @param array{moyenne: float, max: float, min: float} $stats
     */
    private function calculerConfiance(array $stats): float
    {
        $ecart = $stats['max'] - $stats['min'];

        if ($ecart < 20) return 0.9;
        if ($ecart < 50) return 0.75;
        return 0.6;
    }

    private function confianceTexte(float $confiance): string
    {
        if ($confiance >= 0.8) return 'Très élevée';
        if ($confiance >= 0.7) return 'Élevée';
        if ($confiance >= 0.6) return 'Moyenne';
        return 'Faible';
    }

    /**
     * @return array<string, string>
     */
    private function genererRecommandations(int $n, Evenement $evenement): array
    {
        $reco = [
            'materiel'     => sprintf('Prévoir %d chaises, %d tables, %d kits de documentation', $n + 5, intdiv($n, 8) + 1, $n + 10),
            'restauration' => sprintf('Commander %d repas/collations (avec marge de sécurité)', (int) ceil($n * 1.1)),
            'espace'       => sprintf('Prévoir un espace d\'au moins %dm² (salle ou terrain)', $n * 2),
            'staff'        => sprintf('Mobiliser %d personnes pour l\'encadrement', max(2, intdiv($n, 20))),
        ];

        if ($evenement->getType() === 'ATELIER') {
            $reco['semences'] = sprintf('Prévoir des semences/plants pour %d participants + 15%% de marge', $n);
        }

        return $reco;
    }
}
