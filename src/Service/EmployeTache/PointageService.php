<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Pointage;
use App\Repository\EmployeTache\PointageRepository;
use App\Repository\EmployeTache\EmployeRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * 📍 PointageService
 *
 * Gestion du pointage GPS des employés.
 * Validation par formule Haversine — AUCUNE API externe requise.
 *
 * Haversine : distance réelle entre 2 coordonnées GPS sur la sphère terrestre.
 * Rayon Terre : 6 371 000 mètres.
 */
class PointageService
{
    private const RAYON_TERRE_M = 6_371_000;

    // Coordonnées GPS de la ferme (fallback si ferme_config non trouvée)
    private const DEFAULT_LAT = 36.8065;
    private const DEFAULT_LNG = 10.1815;
    private const DEFAULT_RAYON = 500; // mètres

    public function __construct(
        private EntityManagerInterface $em,
        private PointageRepository     $pointageRepo,
        private EmployeRepository      $employeRepo,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    //  ENREGISTREMENT D'UN POINTAGE
    // ══════════════════════════════════════════════════════════════════

    /**
     * Enregistre un pointage GPS et valide la présence sur site.
     *
     * @return array ['success', 'valide', 'distance', 'message', 'pointage']
     */
    public function enregistrerPointage(
        int    $idEmploye,
        int    $idAgriculteur,
        string $type,
        float  $lat,
        float  $lng,
        string $source = Pointage::SOURCE_GPS
    ): array {
        $employe = $this->employeRepo->find($idEmploye);
        if (!$employe || $employe->getIdAgriculteur() !== $idAgriculteur) {
            return ['success' => false, 'message' => 'Employé introuvable.'];
        }

        // Coordonnées de la ferme
        [$fermeLat, $fermeLng, $rayon] = $this->getCoordsFerme($idAgriculteur);

        // Calcul distance Haversine
        $distance = $this->haversine($lat, $lng, $fermeLat, $fermeLng);
        $valide   = $distance <= $rayon;

        // Créer le pointage
        $pointage = new Pointage();
        $pointage->setIdEmploye($idEmploye)
                 ->setIdAgriculteur($idAgriculteur)
                 ->setType($type)
                 ->setLatitude($lat)
                 ->setLongitude($lng)
                 ->setDistanceFerme(round($distance, 2))
                 ->setValide($valide)
                 ->setSource($source);

        $this->em->persist($pointage);
        $this->em->flush();

        $typeLabel = $type === Pointage::TYPE_ARRIVEE ? 'Arrivée' : 'Départ';

        return [
            'success'  => true,
            'valide'   => $valide,
            'distance' => round($distance),
            'rayon'    => $rayon,
            'message'  => $valide
                ? "$typeLabel enregistrée — vous êtes sur site ({$this->fmt($distance)} m de la ferme)"
                : "$typeLabel NON validée — vous êtes à {$this->fmt($distance)} m de la ferme (limite : {$rayon} m)",
            'pointage' => [
                'id'          => $pointage->getId(),
                'type'        => $type,
                'heure'       => $pointage->getHeureFormatee(),
                'distance'    => round($distance),
                'valide'      => $valide,
                'source'      => $source,
                'employe'     => $employe->getNomComplet(),
            ],
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  TABLEAU DE BORD POINTAGE
    // ══════════════════════════════════════════════════════════════════

    public function getDashboardPointage(int $idAgriculteur): array
    {
        $today    = new \DateTime('today');
        $employes = $this->employeRepo->findActifsByAgriculteur($idAgriculteur);

        $presentsAujourd = [];
        $absentsAujourd  = [];
        $historique      = $this->pointageRepo->findByAgriculteurDate($idAgriculteur, $today);

        // Map idEmploye → [arrivee, depart]
        $pointagesMap = [];
        foreach ($historique as $p) {
            $eid = $p->getIdEmploye();
            if (!isset($pointagesMap[$eid])) {
                $pointagesMap[$eid] = ['arrivee' => null, 'depart' => null];
            }
            if ($p->getType() === Pointage::TYPE_ARRIVEE && $p->isValide()) {
                $pointagesMap[$eid]['arrivee'] = $p;
            }
            if ($p->getType() === Pointage::TYPE_DEPART && $p->isValide()) {
                $pointagesMap[$eid]['depart'] = $p;
            }
        }

        foreach ($employes as $emp) {
            $eid = $emp->getId();
            if (isset($pointagesMap[$eid]) && $pointagesMap[$eid]['arrivee'] !== null) {
                $presentsAujourd[] = [
                    'employe' => $emp,
                    'arrivee' => $pointagesMap[$eid]['arrivee'],
                    'depart'  => $pointagesMap[$eid]['depart'],
                ];
            } else {
                $absentsAujourd[] = $emp;
            }
        }

        // Stats 7 derniers jours
        $stats7j = $this->pointageRepo->statsParJour($idAgriculteur, 7);

        [$fermeLat, $fermeLng, $rayon] = $this->getCoordsFerme($idAgriculteur);

        return [
            'presents'      => $presentsAujourd,
            'absents'       => $absentsAujourd,
            'nbPresents'    => count($presentsAujourd),
            'nbAbsents'     => count($absentsAujourd),
            'tauxPresence'  => count($employes) > 0
                ? round(count($presentsAujourd) / count($employes) * 100) : 0,
            'stats7j'       => $stats7j,
            'fermeLat'      => $fermeLat,
            'fermeLng'      => $fermeLng,
            'rayonValidation' => $rayon,
            'totalEmployes' => count($employes),
        ];
    }

    // ══════════════════════════════════════════════════════════════════
    //  FORMULE HAVERSINE — AUCUNE API EXTERNE
    // ══════════════════════════════════════════════════════════════════

    /**
     * Calcule la distance en mètres entre deux points GPS.
     *
     * Formule Haversine :
     *  a = sin²(Δlat/2) + cos(lat1) × cos(lat2) × sin²(Δlon/2)
     *  c = 2 × atan2(√a, √(1−a))
     *  d = R × c
     */
    public function haversine(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $lat1 = deg2rad($lat1);
        $lng1 = deg2rad($lng1);
        $lat2 = deg2rad($lat2);
        $lng2 = deg2rad($lng2);

        $dLat = $lat2 - $lat1;
        $dLng = $lng2 - $lng1;

        $a = sin($dLat / 2) ** 2
           + cos($lat1) * cos($lat2) * sin($dLng / 2) ** 2;

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::RAYON_TERRE_M * $c;
    }

    // ══════════════════════════════════════════════════════════════════
    //  HELPERS
    // ══════════════════════════════════════════════════════════════════

    private function getCoordsFerme(int $idAgriculteur): array
    {
        // Chercher dans ferme_config
        try {
            $conn = $this->em->getConnection();
            $row  = $conn->fetchAssociative(
                'SELECT latitude, longitude, rayon_validation FROM ferme_config WHERE id_agriculteur = ?',
                [$idAgriculteur]
            );
            if ($row) {
                return [(float)$row['latitude'], (float)$row['longitude'], (int)$row['rayon_validation']];
            }
        } catch (\Throwable) {}

        return [self::DEFAULT_LAT, self::DEFAULT_LNG, self::DEFAULT_RAYON];
    }

    private function fmt(float $metres): string
    {
        return number_format($metres, 0, '.', ' ');
    }
}