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
}

class ChatbotService
{
    private MatchingService $matchingService;
    private EmployeRepository $employeRepository;
    private TacheRepository $tacheRepository;
    private PerformanceService $performanceService;
    private TranslatorInterface $translator;
    private RequestStack $requestStack;

    public function __construct(
        MatchingService $matchingService,
        EmployeRepository $employeRepository,
        TacheRepository $tacheRepository,
        PerformanceService $performanceService,
        TranslatorInterface $translator,
        RequestStack $requestStack
    ) {
        $this->matchingService = $matchingService;
        $this->employeRepository = $employeRepository;
        $this->tacheRepository = $tacheRepository;
        $this->performanceService = $performanceService;
        $this->translator = $translator;
        $this->requestStack = $requestStack;
    }

    public function traiterMessage(string $messageUtilisateur, int $idAgriculteur): ChatbotResponse
    {
        $msg = $this->normaliser($messageUtilisateur);
        $lang = $this->detecterLangue($msg);
        $intention = $this->detecterIntention($msg);

        switch ($intention) {
            case "RECOMMANDER_EMPLOYE":
                $response = $this->traiterRecommandation($msg, $idAgriculteur, $lang);
                break;
            case "COMPARER_TOP3":
                $response = $this->traiterComparaisonTop3($msg, $idAgriculteur, $lang);
                break;
            case "RECHERCHER_COMPETENCE":
                $response = $this->traiterRechercheCompetence($msg, $idAgriculteur, $lang);
                break;
            case "ANALYSER_PERFORMANCE":
                $response = $this->traiterAnalysePerformance($idAgriculteur, $lang);
                break;
            case "DISPONIBILITE":
                $response = $this->traiterDisponibilite($idAgriculteur, $lang);
                break;
            case "AIDE":
                $response = $this->genererAide($lang);
                break;
            default:
                $response = new ChatbotResponse();
                $response->reponse = $this->genererReponseDefaut($lang);
        }

        $response->intention = $intention;
        $response->messageUtilisateur = $messageUtilisateur;
        return $response;
    }

    private function detecterLangue(string $message): string
    {
        // 1. Priorité à la détection explicite dans le message
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $message)) return 'ar';
        if (preg_match('/\b(recommend|show|who|can|best|task|employee|performance|available|help|find|give|compare|top)\b/i', $message)) return 'en';
        
        // 2. Repli sur la locale de la session si le message est neutre ou ambigu
        return $this->requestStack->getCurrentRequest()?->getLocale() ?? 'fr';
    }

    private function detecterIntention(string $message): string
    {
        if (preg_match('/compar|compare|top\s*3|top\s*trois|meilleur.*3|3.*meilleur|classement.*employe|podium|مقارنة/iu', $message)) {
            return "COMPARER_TOP3";
        }
        if (preg_match('/recommand|suggest|recommend|propose|meilleur.*(?:employ|pour)|assign|qui peut|trouve.*employ|donne.*employ|best.*employ|who can|find.*employ|يوصي|اوصي|ينصح|من يستطيع|الأفضل/iu', $message)) {
            return "RECOMMANDER_EMPLOYE";
        }
        if (preg_match('/qui sait|comp[eé]tence|connai[ts]|ma[iî]trise|expert|capable|proficient|who knows|skilled|يعرف|خبير|مهارة/iu', $message)) {
            return "RECHERCHER_COMPETENCE";
        }
        if (preg_match('/performance|évaluation|evaluation|classement|statistique|meilleur|top|ranking|أداء|تقييم|ترتيب/iu', $message)) {
            return "ANALYSER_PERFORMANCE";
        }
        if (preg_match('/disponib|libre|occup|charge|peut.*prendre|available|free|workload|متاح|حر|متفرغ/iu', $message)) {
            return "DISPONIBILITE";
        }
        if (preg_match('/^aide$|^help$|comment.*utilis|que.*peux.*faire|what can you|مساعدة/iu', $message)) {
            return "AIDE";
        }
        return "UNKNOWN";
    }

    private function normaliser(string $msg): string
    {
        return strtolower(trim($msg));
    }

    private function buildMsg(string $lang, string $fr, string $en, string $ar): string
    {
        if ($lang === 'ar') return $ar;
        if ($lang === 'en') return $en;
        return $fr;
    }

    // ===================================
    // LOGIQUE METIER
    // ===================================

    private function traiterRecommandation(string $message, int $idAgriculteur, string $lang): ChatbotResponse
    {
        $response = new ChatbotResponse();
        $idTache = $this->extraireIdTache($message, $idAgriculteur);

        if (!$idTache) {
            $response->reponse = $this->translator->trans('chatbot.reco.need_task', [
                '%list%' => "\n" . $this->listerTaches($idAgriculteur)
            ], null, $lang);
            return $response;
        }

        $tache = $this->tacheRepository->find($idTache);
        if (!$tache) {
            $response->reponse = "❌ Tâche introuvable.";
            return $response;
        }

        $assignes = $this->getEmployesActifsAssignesATache($idTache, $idAgriculteur);
        if (!empty($assignes)) {
            $best = $assignes[0];
            $perf = $this->performanceService->calculatePerformance($best->getId());
            
            $r = new RecommandationResult();
            $r->employe = $best;
            $r->scoreTotal = $perf['score'];
            $r->scorePerformance = $perf['score'];
            $r->scoreCompetences = 100.0;
            $r->scoreDisponibilite = 50.0;
            $r->scoreExperience = $perf['tauxReussite'];
            $r->indiceConfiance = 90.0;
            $r->raisonRecommandation = "Déjà assigné à cette tâche.";
            
            $response->reponse = "✅ Employé(s) déjà assigné(s) à \"" . $tache->getTitre() . "\"";
            $response->recommandations[] = $r;
            return $response;
        }

        $recommandations = $this->matchingService->recommanderEmployes($idTache, 3);
        if (!empty($recommandations)) {
            $response->reponse = $this->translator->trans('chatbot.reco.analysis_done', ['%title%' => $tache->getTitre()], null, $lang);
            $response->recommandations = $recommandations;
        } else {
            $response->reponse = $this->translator->trans('chatbot.reco.no_match', [], null, $lang);
        }

        return $response;
    }

    private function traiterComparaisonTop3(string $message, int $idAgriculteur, string $lang): ChatbotResponse
    {
        $response = new ChatbotResponse();
        $idTache = $this->extraireIdTache($message, $idAgriculteur);

        if (!$idTache) {
            $response->reponse = $this->translator->trans('chatbot.compare.need_task', [], null, $lang);
            return $response;
        }

        $tache = $this->tacheRepository->find($idTache);
        $results = $this->matchingService->recommanderEmployes($idTache, 3);

        if (count($results) < 2) {
            $response->reponse = "😕 Moins de 2 employés disponibles pour cette tâche.";
            return $response;
        }

        $response->reponse = "🏆 **Comparaison Top 3 pour \"" . $tache->getTitre() . "\"**";
        $response->recommandations = $results;
        return $response;
    }

    private function traiterRechercheCompetence(string $message, int $idAgriculteur, string $lang): ChatbotResponse
    {
        $response = new ChatbotResponse();
        $competence = $this->extraireCompetence($message);

        if ($competence) {
            $employes = $this->rechercherEmployesActifsParCompetence($competence, $idAgriculteur);
            if (!empty($employes)) {
                $sb = "🔍 **" . count($employes) . " employé(s) avec \"" . $competence . "\" :**\n";
                foreach (array_slice($employes, 0, 5) as $emp) {
                    $sb .= "\n👤 **" . $emp->getPrenom() . " " . $emp->getNom() . "**";
                    if ($emp->getPoste()) $sb .= " — " . $emp->getPoste();
                }
                $response->reponse = $sb;
            } else {
                $response->reponse = $this->translator->trans('chatbot.skills.not_found', ['%comp%' => $competence], null, $lang);
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

        $avecTaches = array_filter($classement, fn($p) => $p['totalTaches'] > 0);
        $avecTaches = array_slice($avecTaches, 0, 5);

        if (!empty($avecTaches)) {
            $sb = "📊 **Classement des Top Performeurs :**\n\n";
            $medals = ["🥇", "🥈", "🥉", "🏅", "⭐"];
            foreach ($avecTaches as $i => $p) {
                $medal = $medals[$i] ?? "⭐️";
                $sb .= sprintf("%s **#%d - %s**\n   💼 Score: %.1f/100 — %s\n", $medal, $i+1, $p['nomEmploye'], $p['score'], $this->performanceService->getAppreciation($p['score']));
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
        $dispos = $this->getDisponibilitesActifs($idAgriculteur);

        if (!empty($dispos)) {
            $response->reponse = "📅 **Disponibilité des " . count($dispos) . " employés actifs :**\n🟢 Disponible • 🟡 Modéré • 🔴 Surchargé";
            $response->disponibilites = $dispos;
        } else {
            $response->reponse = "📅 Aucune information de disponibilité.";
        }
        return $response;
    }

    private function genererAide(string $lang): ChatbotResponse
    {
        $response = new ChatbotResponse();
        $response->reponse = $this->translator->trans('chatbot.help.text', [], null, $lang);
        return $response;
    }

    private function genererReponseDefaut(string $lang): string
    {
        return $this->translator->trans('chatbot.understanding.no_key', [], null, $lang);
    }

    // ===================================
    // METHODES UTILITAIRES DE RECHERCHE
    // ===================================

    private function listerTaches(int $idAgriculteur): string
    {
        $taches = $this->tacheRepository->findBy(['idAgriculteur' => $idAgriculteur]);
        $actives = array_filter($taches, function($t) {
            $s = strtolower($t->getStatut() ?? '');
            return !in_array($s, ['terminé', 'terminee', 'validé', 'validee', 'annulé', 'annulee']);
        });

        if (empty($actives)) return "  (aucune tâche active)\n";
        
        $sb = "";
        foreach ($actives as $t) {
            $sb .= "  • " . $t->getTitre() . "\n";
        }
        return $sb;
    }

    private function extraireIdTache(string $message, int $idAgriculteur): ?int
    {
        if (preg_match('/(?:t[aâ]che|task|مهمة)\s*[#n°]*\s*(\d+)/i', $message, $matches)) {
            return (int)$matches[1];
        }

        // Extraction par titre (très basique pour PHP)
        $taches = $this->tacheRepository->findBy(['idAgriculteur' => $idAgriculteur]);
        foreach ($taches as $t) {
            $titre = strtolower($t->getTitre());
            if (str_contains($message, $titre)) {
                return $t->getId();
            }
        }
        return null; // non trouvé
    }

    private function extraireCompetence(string $message): ?string
    {
        $courantes = ['irrigation', 'tracteur', 'fertilisation', 'récolte', 'plantation', 'taille', 'entretien', 'conduite', 'serre'];
        foreach ($courantes as $c) {
            if (str_contains($message, $c)) return $c;
        }
        if (preg_match('/(?:en|de|in)\s+([a-zA-Z\x{00C0}-\x{00FF}\-]{3,})/i', $message, $m)) {
            return $m[1];
        }
        return null;
    }

    /**
     * @return Employe[]
     */
    private function getEmployesActifsAssignesATache(int $idTache, int $idAgriculteur): array
    {
        $tache = $this->tacheRepository->find($idTache);
        if ($tache && $tache->getIdEmploye()) {
            $emp = $this->employeRepository->find($tache->getIdEmploye());
            if ($emp && $emp->isActif() && $emp->getIdAgriculteur() === $idAgriculteur) {
                return [$emp];
            }
        }
        return [];
    }

    /**
     * @return Employe[]
     */
    private function rechercherEmployesActifsParCompetence(string $competence, int $idAgriculteur): array
    {
        // Simple search in poste
        $qb = $this->employeRepository->createQueryBuilder('e')
            ->where('e.idAgriculteur = :agri')
            ->andWhere('e.actif = true')
            ->andWhere('e.poste LIKE :comp OR e.nom LIKE :comp OR e.prenom LIKE :comp')
            ->setParameter('agri', $idAgriculteur)
            ->setParameter('comp', '%' . $competence . '%')
            ->setMaxResults(10);
        return $qb->getQuery()->getResult();
    }

    /**
     * @return array
     */
    private function getDisponibilitesActifs(int $idAgriculteur): array
    {
        $employes = $this->employeRepository->findActifsByAgriculteur($idAgriculteur);
        $dispos = [];
        foreach ($employes as $emp) {
            $tachesEnCours = $this->tacheRepository->countTachesActivesParEmploye($emp->getId(), $idAgriculteur);
            
            $statut = "Disponible";
            $color = "#27ae60";
            if ($tachesEnCours > 0 && $tachesEnCours <= 2) {
                $statut = "Modéré";
                $color = "#f39c12";
            } elseif ($tachesEnCours > 2) {
                $statut = "Surchargé";
                $color = "#e74c3c";
            }

            $dispos[] = [
                'employe' => [
                    'id' => $emp->getId(),
                    'prenom' => $emp->getPrenom(),
                    'nom' => $emp->getNom(),
                    'poste' => $emp->getPoste(),
                    'telephone' => $emp->getTelephone(),
                    'email' => $emp->getEmail(),
                    'photoPath' => $emp->getPhotoPath()
                ],
                'tachesEnCours' => $tachesEnCours,
                'statutLabel' => $statut,
                'couleurStatut' => $color,
                'dotEmoji' => $tachesEnCours == 0 ? "🟢" : ($tachesEnCours <= 2 ? "🟡" : "🔴")
            ];
        }

        // Trier par moins de charge de travail d'abord
        usort($dispos, function ($a, $b) {
            return $a['tachesEnCours'] <=> $b['tachesEnCours'];
        });

        return $dispos;
    }
}
