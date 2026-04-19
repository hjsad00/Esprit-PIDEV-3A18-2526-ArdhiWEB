<?php

namespace App\Service\EmployeTache;

use App\Entity\EmployeTache\Employe;
use App\Entity\EmployeTache\Tache;
use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;
use Symfony\Contracts\Translation\TranslatorInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class ChatbotResponse
{
    public string $messageUtilisateur = '';
    public string $reponse = '';
    public string $intention = '';
    /** @var RecommandationResult[] */
    public array $recommandations = [];
    /** @var array */
    public array $disponibilites = [];
    /** @var array */
    public array $suggestions = [];
}

/**
 * 🤖 ChatbotService v2 — Améliorations majeures
 *
 * CORRECTIONS :
 *  1. Détection de langue robuste (Unicode ranges + trigrams)
 *  2. Extraction d'ID de tâche fiable (ID explicite prioritaire, puis correspondance floue)
 *  3. Gestion du cas UNKNOWN + suggestions contextuelles
 *  4. Séparation claire intention → handler
 *  5. Injection du classifier ML via constructeur (pas de couplage statique)
 */
class ChatbotService
{
    public function __construct(
        private MatchingService          $matchingService,
        private EmployeRepository        $employeRepository,
        private TacheRepository          $tacheRepository,
        private PerformanceService       $performanceService,
        private TranslatorInterface      $translator,
        private RequestStack             $requestStack,
        private LocalMLIntentClassifier  $mlClassifier,
        private GeminiChatbotService     $geminiService,
        private MeteoService             $meteoService,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    //  POINT D'ENTRÉE
    // ══════════════════════════════════════════════════════════════════

    public function traiterMessage(string $messageUtilisateur, int $idAgriculteur, ?string $lastIntent = null): ChatbotResponse
    {
        $normalized = $this->mlClassifier->normalize($messageUtilisateur);
        $lang       = $this->detecterLangue($messageUtilisateur, $normalized);
        $intention  = $this->mlClassifier->predict($normalized);

        return $this->dispatchIntention($intention, $normalized, $messageUtilisateur, $idAgriculteur, $lang, $lastIntent);
    }

    /**
     * Bypass ML complet : intention fournie directement par le controller (boutons rapides).
     */
    public function traiterMessageAvecIntention(
        string  $messageUtilisateur,
        string  $intention,
        int     $idAgriculteur,
        ?string $lastIntent = null
    ): ChatbotResponse {
        $normalized = $this->mlClassifier->normalize($messageUtilisateur);
        $lang       = $this->detecterLangue($messageUtilisateur, $normalized);

        return $this->dispatchIntention($intention, $normalized, $messageUtilisateur, $idAgriculteur, $lang, $lastIntent);
    }

    /**
     * Dispatch vers le bon handler selon l'intention.
     */
    private function dispatchIntention(
        string  $intention,
        string  $normalized,
        string  $messageOriginal,
        int     $idAgriculteur,
        string  $lang,
        ?string $lastIntent = null
    ): ChatbotResponse {
        $response = match ($intention) {
            'RECOMMANDER_EMPLOYE'   => $this->traiterRecommandation($normalized, $idAgriculteur, $lang),
            'COMPARER_TOP3'         => $this->traiterComparaisonTop3($normalized, $idAgriculteur, $lang),
            'RECHERCHER_COMPETENCE' => $this->traiterRechercheCompetence($normalized, $idAgriculteur, $lang),
            'ANALYSER_PERFORMANCE'  => $this->traiterAnalysePerformance($idAgriculteur, $lang),
            'DISPONIBILITE'         => $this->traiterDisponibilite($idAgriculteur, $lang),
            'METEO'                 => $this->traiterMeteo($idAgriculteur, $lang),
            'AIDE'                  => $this->genererAide($lang),
            default                 => $this->traiterUnknown($normalized, $messageOriginal, $idAgriculteur, $lang, $lastIntent),
        };

        $response->intention          = $intention;
        $response->messageUtilisateur = $messageOriginal;
        return $response;
    }

    // ══════════════════════════════════════════════════════════════════
    //  DÉTECTION DE LANGUE (v2 — robuste)
    // ══════════════════════════════════════════════════════════════════

    /**
     * Détecte la langue avec priorité :
     *  1. Présence de caractères arabes Unicode → 'ar'
     *  2. Mots-clés anglais fréquents → 'en'
     *  3. Mots-clés français → 'fr'
     *  4. Fallback sur locale de session
     */
    private function detecterLangue(string $original, string $normalized): string
    {
        // 1. Arabe (plage Unicode U+0600–U+06FF)
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $original)) {
            return 'ar';
        }

        // 2. Anglais — liste de mots discriminants
        $englishMarkers = ['who', 'which', 'what', 'show', 'find', 'best', 'can', 'available',
                           'recommend', 'suggest', 'compare', 'performance', 'task', 'help', 'employee'];
        foreach ($englishMarkers as $word) {
            if (preg_match('/\b' . $word . '\b/i', $normalized)) {
                return 'en';
            }
        }

        // 3. Français — mots caractéristiques
        $frenchMarkers = ['qui', 'quel', 'quels', 'donne', 'montre', 'employe', 'tache',
                          'recommande', 'compare', 'libre', 'disponible', 'meilleur', 'aide'];
        foreach ($frenchMarkers as $word) {
            if (preg_match('/\b' . $word . '\b/i', $normalized)) {
                return 'fr';
            }
        }

        // 4. Locale session
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
    }

    // ══════════════════════════════════════════════════════════════════
    //  HANDLERS D'INTENTION
    // ══════════════════════════════════════════════════════════════════

    private function traiterRecommandation(string $message, int $idAgriculteur, string $lang): ChatbotResponse
    {
        $response = new ChatbotResponse();

        [$idTache, $confidence] = $this->extraireIdTache($message, $idAgriculteur);

        if ($idTache === null || $confidence < 1.0) {
            // Si ambiguïté (confidence < 1.0) ou non trouvé (null) → lister et proposer des suggestions
            $response->reponse = $this->translator->trans('chatbot.reco.need_task', [
                '%list%' => "\n" . $this->listerTachesActives($idAgriculteur),
            ], null, $lang);

            // Ajouter des suggestions cliquables
            $actives = $this->findTachesActives($idAgriculteur);
            foreach ($actives as $t) {
                $response->suggestions[] = [
                    'text'   => $t->getTitre(),
                    'msg'    => "Tâche #" . $t->getId(),
                    'action' => 'RECOMMANDER_EMPLOYE'
                ];
            }
            return $response;
        }

        $tache = $this->tacheRepository->find($idTache);
        if (!$tache) {
            $response->reponse = $this->translator->trans('chatbot.reco.task_not_found', [], null, $lang);
            return $response;
        }

        // Si déjà assignée à un employé actif → afficher cet employé en premier
        $employeActif = $this->getEmployeActifDeTache($tache, $idAgriculteur);
        if ($employeActif !== null) {
            $perf = $this->performanceService->calculatePerformance($employeActif->getId());
            $r = $this->buildRecommandationDepuisEmploye($employeActif, $perf, $lang, true);
            $response->reponse = $this->translator->trans(
                'chatbot.reco.already_assigned',
                ['%task%' => $tache->getTitre()],
                null,
                $lang
            );
            $response->recommandations[] = $r;
            return $response;
        }

        $recommandations = $this->matchingService->recommanderEmployes($idTache, 3);
        if (!empty($recommandations)) {
            $note = $confidence < 1.0
                ? "\n_(Tâche détectée par correspondance approximative)_"
                : '';
            $response->reponse = $this->translator->trans(
                'chatbot.reco.analysis_done',
                ['%title%' => $tache->getTitre()],
                null,
                $lang
            ) . $note;
            $response->recommandations = $recommandations;
        } else {
            $response->reponse = $this->translator->trans('chatbot.reco.no_match', [], null, $lang);
        }

        return $response;
    }

    private function traiterComparaisonTop3(string $message, int $idAgriculteur, string $lang): ChatbotResponse
    {
        $response = new ChatbotResponse();
        [$idTache] = $this->extraireIdTache($message, $idAgriculteur);

        if ($idTache === null) {
            $response->reponse = $this->translator->trans('chatbot.compare.need_task', [], null, $lang)
                . "\n" . $this->listerTachesActives($idAgriculteur);
            return $response;
        }

        $tache   = $this->tacheRepository->find($idTache);
        $results = $this->matchingService->recommanderEmployes($idTache, 3);

        if (count($results) < 2) {
            $response->reponse = $this->translator->trans('chatbot.compare.not_enough_employees', [], null, $lang);
            return $response;
        }

        $response->reponse = $this->translator->trans(
            'chatbot.compare.title',
            ['%task%' => $tache?->getTitre() ?? '#' . $idTache],
            null,
            $lang
        );
        $response->recommandations = $results;
        return $response;
    }

    private function traiterRechercheCompetence(string $message, int $idAgriculteur, string $lang): ChatbotResponse
    {
        $response = new ChatbotResponse();
        $competence = $this->extraireCompetence($message);

        if ($competence !== null) {
            $employes = $this->rechercherEmployesParCompetence($competence, $idAgriculteur);
            if (!empty($employes)) {
                $lines = $this->translator->trans(
                    'chatbot.skills.found_title',
                    ['%count%' => count($employes), '%comp%' => $competence],
                    null,
                    $lang
                ) . "\n";
                foreach (array_slice($employes, 0, 5) as $emp) {
                    $lines .= "\n👤 **{$emp->getPrenom()} {$emp->getNom()}**";
                    if ($emp->getPoste()) {
                        $lines .= " — {$emp->getPoste()}";
                    }
                }
                $response->reponse = $lines;
            } else {
                $response->reponse = $this->translator->trans(
                    'chatbot.skills.not_found',
                    ['%comp%' => $competence],
                    null,
                    $lang
                );
            }
        } else {
            $response->reponse = $this->translator->trans('chatbot.skills.prompt', [], null, $lang);
        }

        return $response;
    }

    private function traiterAnalysePerformance(int $idAgriculteur, string $lang): ChatbotResponse
    {
        $response = new ChatbotResponse();
        $classement = $this->performanceService->getClassement($idAgriculteur);
        $avecTaches = array_slice(
            array_filter($classement, fn($p) => $p['totalTaches'] > 0),
            0, 5
        );

        if (!empty($avecTaches)) {
            $medals = ['🥇', '🥈', '🥉', '🏅', '⭐'];
            $sb     = $this->translator->trans('chatbot.performance.title', [], null, $lang) . "\n\n";
            foreach (array_values($avecTaches) as $i => $p) {
                $medal  = $medals[$i] ?? '⭐️';
                $line   = $this->translator->trans('chatbot.performance.score_line', [
                    '%score%'        => sprintf('%.1f', $p['score']),
                    '%appreciation%' => $this->performanceService->getAppreciation($p['score']),
                ], null, $lang);
                $sb .= sprintf("%s **#%d — %s**\n   %s\n", $medal, $i + 1, $p['nomEmploye'], $line);
            }
            $response->reponse = $sb;
        } else {
            $response->reponse = $this->translator->trans('chatbot.performance.no_data', [], null, $lang);
        }

        return $response;
    }

    private function traiterDisponibilite(int $idAgriculteur, string $lang): ChatbotResponse
    {
        $response = new ChatbotResponse();
        $dispos   = $this->construireDisponibilites($idAgriculteur, $lang);

        if (!empty($dispos)) {
            $response->reponse       = $this->translator->trans(
                'chatbot.availability.title_with_modes',
                ['%count%' => count($dispos)],
                null,
                $lang
            );
            $response->disponibilites = $dispos;
        } else {
            $response->reponse = $this->translator->trans('chatbot.availability.no_info', [], null, $lang);
        }

        return $response;
    }

    private function genererAide(string $lang): ChatbotResponse
    {
        $response = new ChatbotResponse();
        $response->reponse = $this->translator->trans('chatbot.help.text', [], null, $lang);
        return $response;
    }

    /**
     * Gestionnaire UNKNOWN :
     *  1. Tente de détecter un ID de tâche → relance recommandation
     *  2. Sinon → délègue à Gemini AI pour une réponse intelligente
     */
    /**
     * Handler METEO : Donne les conditions actuelles et les conseils proactifs.
     */
    private function traiterMeteo(int $idAgriculteur, string $lang): ChatbotResponse {
        $w = $this->meteoService->getCurrentWeather();
        $response = new ChatbotResponse();
        
        if (!$w->isAvailable()) {
            $response->reponse = $this->translator->trans('meteo.widget_title', [], null, $lang) . " : " . $this->translator->trans('notification.empty', [], null, $lang);
            return $response;
        }

        $intro = sprintf("🌡️ **Météo à %s : %d°C (%s)**\n\n", $w->getCityName(), round($w->getTemperature()), $w->getDescription());
        $advice = $this->meteoService->genererRecommandationsGenerales($w);
        
        $sb = $intro;
        if (!empty($advice)) {
            $sb .= "💡 **Conseils du jour :**\n";
            foreach ($advice as $r) {
                $sb .= "• " . $r->message . "\n";
            }
        } else {
            $sb .= "Aucune recommandation spécifique pour ces conditions.";
        }
        
        $response->reponse = $sb;
        return $response;
    }

    private function traiterUnknown(
        string  $message,
        string  $messageOriginal,
        int     $idAgriculteur,
        string  $lang,
        ?string $lastIntent = null
    ): ChatbotResponse {
        // 1. Tenter de détecter un ID de tâche dans le message → suggérer recommandation
        [$idTache] = $this->extraireIdTache($message, $idAgriculteur);
        if ($idTache !== null) {
            return $this->traiterRecommandation($message, $idAgriculteur, $lang);
        }

        // 2. Déléguer à Gemini AI — réponse intelligente et contextualisée
        return $this->geminiService->generateResponse(
            $messageOriginal,
            $idAgriculteur,
            $lang,
            $lastIntent
        );
    }

    // ══════════════════════════════════════════════════════════════════
    //  EXTRACTION DE TÂCHE (v2 — plus fiable)
    // ══════════════════════════════════════════════════════════════════

    /**
     * Extrait l'ID d'une tâche depuis le message.
     * Retourne [?int $id, float $confidence] :
     *   - confidence = 1.0 → ID explicite trouvé (ex: "tâche #12")
     *   - confidence < 1.0 → correspondance par titre (peut être ambigu)
     *
     * @return array{0: int|null, 1: float}
     */
    private function extraireIdTache(string $message, int $idAgriculteur): array
    {
        // 1. ID explicite (priorité absolue) : "tâche 12", "task #5", "n°3", "مهمة 7"
        if (preg_match('/(?:t[aâ]che|task|مهمة|مهمه|raqm)\s*[#n°]*\s*(\d+)/ui', $message, $m)) {
            return [(int)$m[1], 1.0];
        }

        // 2. Numéro isolé dans le message (ex: "pour le 12", "numéro 5")
        if (preg_match('/(?:num[eé]ro|numéro|le|pour le|pour la)\s+(\d+)\b/ui', $message, $m)) {
            $id    = (int)$m[1];
            $tache = $this->tacheRepository->find($id);
            if ($tache && $tache->getIdAgriculteur() === $idAgriculteur) {
                return [$id, 1.0];
            }
        }

        // 3. Correspondance floue par titre (Levenshtein) — uniquement si score assez élevé
        $taches = $this->tacheRepository->findBy(['idAgriculteur' => $idAgriculteur]);
        $bestId    = null;
        $bestScore = 0.0;

        foreach ($taches as $tache) {
            $titre        = $this->mlClassifier->normalize($tache->getTitre());
            $similarity   = 0;
            similar_text($message, $titre, $similarity);

            // Exiger au moins 85% de similarité pour éviter les faux positifs automatiques
            if ($similarity > $bestScore && $similarity >= 85.0) {
                $bestScore = $similarity;
                $bestId    = $tache->getId();
            }
        }

        return [$bestId, $bestScore / 100];
    }

    // ══════════════════════════════════════════════════════════════════
    //  UTILITAIRES MÉTIER
    // ══════════════════════════════════════════════════════════════════

    private function listerTachesActives(int $idAgriculteur): string
    {
        $actives = $this->findTachesActives($idAgriculteur);

        if (empty($actives)) {
            return "  _(Aucune tâche active)_\n";
        }

        $sb = "";
        foreach ($actives as $t) {
            $sb .= "  • **#{$t->getId()}** — {$t->getTitre()}\n";
        }
        return $sb;
    }

    /**
     * @return Tache[]
     */
    private function findTachesActives(int $idAgriculteur): array
    {
        $taches = $this->tacheRepository->findBy(['idAgriculteur' => $idAgriculteur]);
        $statuts_termines = ['terminé', 'terminee', 'validé', 'validee', 'annulé', 'annulee'];

        return array_filter($taches, fn($t) =>
            !in_array(strtolower($t->getStatut() ?? ''), $statuts_termines, true)
        );
    }

    private function getEmployeActifDeTache(Tache $tache, int $idAgriculteur): ?Employe
    {
        if ($tache->getIdEmploye() === null) {
            return null;
        }
        $emp = $this->employeRepository->find($tache->getIdEmploye());
        if ($emp && $emp->isActif() && $emp->getIdAgriculteur() === $idAgriculteur) {
            return $emp;
        }
        return null;
    }

    private function buildRecommandationDepuisEmploye(
        Employe $employe,
        array   $perf,
        string  $lang,
        bool    $alreadyAssigned = false
    ): RecommandationResult {
        $r = new RecommandationResult();
        $r->employe               = $employe;
        $r->scoreTotal            = $perf['score'];
        $r->scorePerformance      = $perf['score'];
        $r->scoreCompetences      = 100.0;
        $r->scoreDisponibilite    = 50.0;
        $r->scoreExperience       = $perf['tauxReussite'];
        $r->indiceConfiance       = $alreadyAssigned ? 95.0 : min(100.0, $perf['score'] + 10);
        $r->raisonRecommandation  = $alreadyAssigned
            ? $this->translator->trans('chatbot.reco.already_assigned_reason', [], null, $lang)
            : '';
        return $r;
    }

    private function extraireCompetence(string $message): ?string
    {
        // Liste étendue de compétences agricoles communes
        $competences = [
            'irrigation', 'tracteur', 'fertilisation', 'recolte', 'plantation',
            'taille', 'entretien', 'conduite', 'serre', 'arrosage', 'semis',
            'epandage', 'binage', 'greffe', 'desherbage', 'machinerie',
        ];

        foreach ($competences as $c) {
            if (str_contains($message, $c)) {
                return $c;
            }
        }

        // Extraction générique : mot après "en", "de", "pour", "in"
        if (preg_match('/(?:en|de|pour|in|with)\s+([\p{L}\-]{4,})/iu', $message, $m)) {
            return $m[1];
        }

        return null;
    }

    private function rechercherEmployesParCompetence(string $competence, int $idAgriculteur): array
    {
        return $this->employeRepository->createQueryBuilder('e')
            ->where('e.idAgriculteur = :agri')
            ->andWhere('e.actif = true')
            ->andWhere('e.poste LIKE :comp OR e.nom LIKE :comp OR e.prenom LIKE :comp')
            ->setParameter('agri', $idAgriculteur)
            ->setParameter('comp', '%' . $competence . '%')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();
    }

    private function construireDisponibilites(int $idAgriculteur, string $lang): array
    {
        $employes = $this->employeRepository->findActifsByAgriculteur($idAgriculteur);
        $dispos   = [];

        foreach ($employes as $emp) {
            $nb = $this->tacheRepository->countTachesActivesParEmploye($emp->getId(), $idAgriculteur);

            [$statut, $color, $dot] = match (true) {
                $nb === 0   => [$this->translator->trans('status.available', [], null, $lang), '#27ae60', '🟢'],
                $nb <= 2    => [$this->translator->trans('status.moderate',  [], null, $lang), '#f39c12', '🟡'],
                default     => [$this->translator->trans('status.overloaded', [], null, $lang), '#e74c3c', '🔴'],
            };

            $dispos[] = [
                'employe'       => [
                    'id'        => $emp->getId(),
                    'prenom'    => $emp->getPrenom(),
                    'nom'       => $emp->getNom(),
                    'poste'     => $emp->getPoste(),
                    'telephone' => $emp->getTelephone(),
                    'email'     => $emp->getEmail(),
                    'photoPath' => $emp->getPhotoPath(),
                ],
                'tachesEnCours'  => $nb,
                'statutLabel'    => $statut,
                'couleurStatut'  => $color,
                'dotEmoji'       => $dot,
            ];
        }

        // Trier : moins chargé en premier
        usort($dispos, fn($a, $b) => $a['tachesEnCours'] <=> $b['tachesEnCours']);

        return $dispos;
    }
}