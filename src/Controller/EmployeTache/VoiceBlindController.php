<?php

namespace App\Controller\EmployeTache;

use App\Entity\EmployeTache\Tache;
use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;
use App\Service\EmployeTache\AgriculteurContextService;
use App\Service\EmployeTache\EmployeAutoInactifService;
use App\Service\EmployeTache\TacheRiskService;
use App\Service\EmployeTache\UrgentNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * 🎙️ API dédiée à l'assistant vocal pour non-voyants
 *
 * Endpoints :
 *   POST /voice-blind/session      — Démarre une session vocale
 *   POST /voice-blind/taches/lire  — Lit toutes les tâches
 *   POST /voice-blind/tache/creer  — Crée une tâche depuis la voix
 *   GET  /voice-blind/employes     — Liste des employés (pour assignation)
 *   GET  /voice-blind/csrf         — Génère un token CSRF
 */
#[Route('/voice-blind', name: 'voice_blind_')]
class VoiceBlindController extends AbstractController
{
    public function __construct(
        private AgriculteurContextService  $ctx,
        private TacheRepository            $tacheRepo,
        private EmployeRepository          $empRepo,
        private EntityManagerInterface     $em,
        private EmployeAutoInactifService  $autoInactif,
        private TacheRiskService           $riskService,
        private UrgentNotificationService  $urgentNotif,
        private CsrfTokenManagerInterface  $csrfTokenManager,
    ) {}

    private function getAgriculteurId(): ?int
    {
        // Pour les requêtes AJAX de l'assistant vocal,
        // on tente aussi de récupérer l'ID même sans supervision active
        if (!$this->ctx->hasAccess()) return null;
        $id = $this->ctx->getActiveAgriculteurId();
        if ($id !== null) return $id;

        // Fallback : si l'utilisateur est un agriculteur connecté,
        // utilise son propre ID
        $user = $this->getUser();
        if ($user && method_exists($user, 'getId')) {
            return (int) $user->getId();
        }
        return null;
    }

    // ══════════════════════════════════════════════════════════════════
    //  GET /voice-blind/taches — Liste toutes les tâches
    // ══════════════════════════════════════════════════════════════════
    #[Route('/taches', name: 'taches', methods: ['GET'])]
    public function lireTaches(): JsonResponse
    {
        $id = $this->getAgriculteurId();
        if (!$id) return new JsonResponse(['error' => 'Accès refusé'], 403);

        $taches   = $this->tacheRepo->findByAgriculteur($id);
        $employes = $this->empRepo->findByAgriculteur($id);

        $mapEmp = [];
        foreach ($employes as $emp) {
            if ($emp->getId()) $mapEmp[$emp->getId()] = $emp->getNomComplet();
        }

        $prios = [1 => 'basse', 2 => 'moyenne', 3 => 'haute', 4 => 'critique'];

        // Construire le texte de lecture pour chaque tâche
        $items = [];
        foreach ($taches as $i => $t) {
            $num    = $i + 1;
            $prio   = $prios[$t->getPriorite() ?? 2] ?? 'normale';
            $emp    = $t->getIdEmploye() ? ($mapEmp[$t->getIdEmploye()] ?? 'non assignée') : 'non assignée';
            $retard = $t->isEnRetard() ? ' Cette tâche est en retard.' : '';
            $fin    = $t->getDateFin() ? ' Échéance le ' . $t->getDateFin()->format('d/m/Y') . '.' : '';

            $items[] = [
                'id'     => $t->getId(),
                'numero' => $num,
                'texte'  => "Tâche {$num} : {$t->getTitre()}. "
                          . "Statut : {$t->getStatut()}. "
                          . "Priorité {$prio}. "
                          . "Assignée à {$emp}."
                          . $fin
                          . $retard,
                'titre'   => $t->getTitre(),
                'statut'  => $t->getStatut(),
                'priorite'=> $prio,
                'employe' => $emp,
                'enRetard'=> $t->isEnRetard(),
            ];
        }

        $intro = count($items) === 0
            ? "Vous n'avez aucune tâche pour le moment."
            : "Vous avez " . count($items) . " tâche" . (count($items) > 1 ? 's' : '') . ". ";

        return new JsonResponse([
            'intro' => $intro,
            'taches'=> $items,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    //  GET /voice-blind/employes — Liste des employés actifs
    // ══════════════════════════════════════════════════════════════════
    #[Route('/employes', name: 'employes', methods: ['GET'])]
    public function listeEmployes(): JsonResponse
    {
        $id = $this->getAgriculteurId();
        if (!$id) return new JsonResponse(['error' => 'Accès refusé'], 403);

        $employes = $this->empRepo->findActifsByAgriculteur($id);

        $items = [];
        foreach ($employes as $i => $emp) {
            $items[] = [
                'id'     => $emp->getId(),
                'numero' => $i + 1,
                'nom'    => $emp->getNomComplet(),
                'poste'  => $emp->getPoste() ?? 'sans poste',
                'texte'  => ($i + 1) . ' : ' . $emp->getNomComplet()
                          . ($emp->getPoste() ? ', ' . $emp->getPoste() : ''),
            ];
        }

        return new JsonResponse([
            'intro'   => count($items) . " employé" . (count($items) > 1 ? 's' : '') . " disponible" . (count($items) > 1 ? 's' : '') . ".",
            'employes'=> $items,
        ]);
    }

    // ══════════════════════════════════════════════════════════════════
    //  GET /voice-blind/csrf — Token CSRF pour création
    // ══════════════════════════════════════════════════════════════════
    #[Route('/csrf', name: 'csrf', methods: ['GET'])]
    public function csrf(): JsonResponse
    {
        $token = $this->csrfTokenManager->getToken('tache_form')->getValue();
        return new JsonResponse(['token' => $token]);
    }

    // ══════════════════════════════════════════════════════════════════
    //  POST /voice-blind/tache/creer
    //  Crée une tâche directement depuis les données vocales.
    //  Body JSON attendu :
    //  {
    //    "titre":      "Irriguer le champ nord",
    //    "categorie":  "Irrigation",      (optionnel, défaut: Plantation)
    //    "priorite":   3,                 (1=Basse, 2=Moyenne, 3=Haute, 4=Critique)
    //    "idEmploye":  12,                (optionnel)
    //    "dateDebut":  "2026-05-10",      (optionnel, défaut: aujourd'hui)
    //    "dateFin":    "2026-05-15",      (optionnel)
    //    "description":"...",             (optionnel)
    //    "_token":     "csrf_token"
    //  }
    // ══════════════════════════════════════════════════════════════════
    #[Route('/tache/creer', name: 'tache_creer', methods: ['POST'])]
    public function creerTache(Request $request): JsonResponse
    {
        $idAgriculteur = $this->getAgriculteurId();
        if (!$idAgriculteur) {
            return new JsonResponse(['success' => false, 'message' => 'Accès refusé.'], 403);
        }

        $data = json_decode($request->getContent(), true) ?? [];

        // Validation CSRF
        if (!$this->isCsrfTokenValid('tache_form', (string) ($data['_token'] ?? ''))) {
            return new JsonResponse(['success' => false, 'message' => 'Token de sécurité invalide.'], 403);
        }

        // Validation titre (obligatoire)
        $titre = trim($data['titre'] ?? '');
        if ($titre === '') {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le titre de la tâche est obligatoire.',
                'champ'   => 'titre',
            ]);
        }
        if (strlen($titre) > 200) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Le titre est trop long. Maximum 200 caractères.',
                'champ'   => 'titre',
            ]);
        }

        // Catégorie
        $categoriesValides = Tache::CATEGORIES;
        $categorie = $data['categorie'] ?? 'Plantation';
        if (!in_array($categorie, $categoriesValides, true)) {
            $categorie = 'Plantation';
        }

        // Priorité
        $priorite = isset($data['priorite']) ? (int) $data['priorite'] : 2;
        if (!in_array($priorite, [1, 2, 3, 4], true)) $priorite = 2;

        // Dates
        $dateDebut = null;
        $dateFin   = null;
        try {
            $dateDebut = isset($data['dateDebut']) && $data['dateDebut']
                ? new \DateTime($data['dateDebut'])
                : new \DateTime('today');
        } catch (\Exception) {
            $dateDebut = new \DateTime('today');
        }
        try {
            $dateFin = isset($data['dateFin']) && $data['dateFin']
                ? new \DateTime($data['dateFin'])
                : null;
        } catch (\Exception) {
            $dateFin = null;
        }

        // Validation date cohérence
        if ($dateFin && $dateDebut && $dateFin < $dateDebut) {
            return new JsonResponse([
                'success' => false,
                'message' => 'La date de fin doit être après la date de début.',
                'champ'   => 'dateFin',
            ]);
        }

        // Employé
        $idEmploye = isset($data['idEmploye']) && $data['idEmploye'] ? (int) $data['idEmploye'] : null;
        if ($idEmploye) {
            $emp = $this->empRepo->find($idEmploye);
            if (!$emp || $emp->getIdAgriculteur() !== $idAgriculteur) {
                return new JsonResponse([
                    'success' => false,
                    'message' => "Employé introuvable.",
                    'champ'   => 'idEmploye',
                ]);
            }
        }

        // ── Création ───────────────────────────────────────────────────
        $tache = new Tache();
        $tache->setTitre($titre);
        $tache->setDescription(trim($data['description'] ?? '') ?: null);
        $tache->setStatut(Tache::STATUT_EN_ATTENTE);
        $tache->setPriorite($priorite);
        $tache->setCategorie($categorie);
        $tache->setDateDebut($dateDebut);
        $tache->setDateFin($dateFin);
        $tache->setIdEmploye($idEmploye);
        $tache->setIdAgriculteur($idAgriculteur);

        $this->em->persist($tache);
        $this->em->flush();

        // ── Post-création (réactivation employé + alertes) ─────────────
        $msgAlerte = null;
        if ($idEmploye) {
            $this->autoInactif->synchroniserEmploye($idEmploye, $idAgriculteur);
            $employe = $this->empRepo->find($idEmploye);
            if ($employe && $employe->isActif()) {
                if ($priorite === 4) {
                    $msg = "⚠️ ALERTE ARDHI: La tâche critique '{$titre}' vous a été assignée.";
                    $this->urgentNotif->sendUrgentNotification($employe, $msg, 'both');
                    $msgAlerte = "Une alerte urgente a été envoyée à " . $employe->getNomComplet() . ".";
                } else {
                    $resultatRisk = $this->riskService->analyser($tache, $employe->getNomComplet());
                    if (isset($resultatRisk['riskScore']) && $resultatRisk['riskScore'] > 75) {
                        $msg = "🚨 ALERTE RISQUE: La tâche '{$titre}' est à haut risque d'échec.";
                        $this->urgentNotif->sendUrgentNotification($employe, $msg, 'both');
                        $msgAlerte = "Attention, cette tâche présente un risque élevé d'échec.";
                    }
                }
            }
        }

        // ── Réponse vocale de confirmation ─────────────────────────────
        $prios  = [1 => 'basse', 2 => 'moyenne', 3 => 'haute', 4 => 'critique'];
        $empNom = $idEmploye ? ($this->empRepo->find($idEmploye)?->getNomComplet() ?? '') : '';

        $confirmation = "Tâche créée avec succès. ";
        $confirmation .= "Titre : {$titre}. ";
        $confirmation .= "Catégorie : {$categorie}. ";
        $confirmation .= "Priorité : " . ($prios[$priorite] ?? 'normale') . ". ";
        if ($empNom) $confirmation .= "Assignée à {$empNom}. ";
        if ($dateFin) $confirmation .= "Échéance le " . $dateFin->format('d/m/Y') . ". ";
        if ($msgAlerte) $confirmation .= $msgAlerte;

        return new JsonResponse([
            'success'      => true,
            'message'      => $confirmation,
            'tache_id'     => $tache->getId(),
            'tache_titre'  => $titre,
        ]);
    }
}