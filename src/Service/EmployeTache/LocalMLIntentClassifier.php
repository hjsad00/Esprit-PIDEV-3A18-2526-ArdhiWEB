<?php

namespace App\Service\EmployeTache;

use Phpml\Classification\SVC;
use Phpml\SupportVectorMachine\Kernel;
use Phpml\FeatureExtraction\TokenCountVectorizer;
use Phpml\FeatureExtraction\StopWords\French;
use Phpml\Tokenization\WordTokenizer;
use Phpml\FeatureExtraction\TfIdfTransformer;
use Phpml\Pipeline;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;

/**
 * 🤖 Classifieur d'intentions ML local (PHP-ML)
 *
 * AMÉLIORATIONS v2 :
 *  - Cache du modèle entraîné (FilesystemAdapter) → entraînement UNE SEULE FOIS
 *  - Dataset trilingue : français + anglais + arabe/francarabe tunisien
 *  - Fallback règles regex si ML échoue ou score trop bas
 *  - Normalisation Unicode (suppression diacritiques)
 */
class LocalMLIntentClassifier
{
    private const CACHE_KEY  = 'rh_chatbot_ml_pipeline_v5';
    private const CACHE_TTL  = 86400 * 7; // 7 jours

    private ?Pipeline $pipeline = null;
    private FilesystemAdapter $cache;

    public function __construct(string $cacheDir = '/tmp')
    {
        $this->cache = new FilesystemAdapter('chatbot_ml', self::CACHE_TTL, $cacheDir);
    }

    // ══════════════════════════════════════════════════════════════════
    //  API PUBLIQUE
    // ══════════════════════════════════════════════════════════════════

    public function predict(string $message): string
    {
        $normalized = $this->normalize($message);

        if (strlen($normalized) < 2) {
            return 'UNKNOWN';
        }

        // 0. Commandes exactes des boutons rapides (priorité absolue, avant tout)
        $exact = $this->exactCommandMatch($normalized);
        if ($exact !== null) {
            return $exact;
        }

        // 1. Fallback règles (déterministe, 0ms)
        $fallback = $this->ruleBasedFallback($normalized);
        if ($fallback !== null) {
            return $fallback;
        }

        // 2. Prédiction ML (modèle mis en cache)
        try {
            $pipeline = $this->getPipeline();
            return $pipeline->predict([$normalized])[0] ?? 'UNKNOWN';
        } catch (\Throwable $e) {
            return 'UNKNOWN';
        }
    }

    /**
     * Correspondance exacte pour les boutons rapides de l'UI.
     * Ces mots courts (1-2 mots) sont impossibles à classifier correctement par le ML.
     * Trilingue : Fr / En / Ar
     */
    private function exactCommandMatch(string $msg): ?string
    {
        // Tableau [mot_normalisé => intention]
        // On normalise aussi les clés pour ignorer les accents
        $commands = [
            // ANALYSER_PERFORMANCE — doit être AVANT disponibilite dans la table
            'performances'        => 'ANALYSER_PERFORMANCE',
            'performance'         => 'ANALYSER_PERFORMANCE',
            'performences'        => 'ANALYSER_PERFORMANCE', // faute fréquente
            'stats'               => 'ANALYSER_PERFORMANCE',
            'statistiques'        => 'ANALYSER_PERFORMANCE',
            'scores'              => 'ANALYSER_PERFORMANCE',
            'classement'          => 'ANALYSER_PERFORMANCE',
            'bilan'               => 'ANALYSER_PERFORMANCE',

            // RECOMMANDER_EMPLOYE
            'recommander'         => 'RECOMMANDER_EMPLOYE',
            'recommande'          => 'RECOMMANDER_EMPLOYE',
            'recommend'           => 'RECOMMANDER_EMPLOYE',
            'suggestion'          => 'RECOMMANDER_EMPLOYE',

            // DISPONIBILITE
            'disponibilite'       => 'DISPONIBILITE',
            'disponibilites'      => 'DISPONIBILITE',
            'disponible'          => 'DISPONIBILITE',
            'availability'        => 'DISPONIBILITE',
            'charge'              => 'DISPONIBILITE',

            // COMPARER_TOP3
            'comparer'            => 'COMPARER_TOP3',
            'compare'             => 'COMPARER_TOP3',
            'top3'                => 'COMPARER_TOP3',
            'top 3'               => 'COMPARER_TOP3',
            'podium'              => 'COMPARER_TOP3',

            // RECHERCHER_COMPETENCE
            'competence'          => 'RECHERCHER_COMPETENCE',
            'competences'         => 'RECHERCHER_COMPETENCE',
            'skills'              => 'RECHERCHER_COMPETENCE',

            // AIDE
            'aide'                => 'AIDE',
            'help'                => 'AIDE',
            'ساعد'                => 'AIDE',
            'مساعدة'              => 'AIDE',
        ];

        // Correspondance exacte sur le message entier (après normalisation)
        if (isset($commands[$msg])) {
            return $commands[$msg];
        }

        // Correspondance si le message est une commande + ponctuation résiduelle
        $clean = trim($msg, ' .,!?');
        if (isset($commands[$clean])) {
            return $commands[$clean];
        }

        return null;
    }

    /**
     * Force le ré-entraînement du modèle (utile après ajout de données).
     */
    public function resetCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
        $this->pipeline = null;
    }

    // ══════════════════════════════════════════════════════════════════
    //  PIPELINE ML
    // ══════════════════════════════════════════════════════════════════

    private function getPipeline(): Pipeline
    {
        if ($this->pipeline !== null) {
            return $this->pipeline;
        }

        $item = $this->cache->getItem(self::CACHE_KEY);

        if ($item->isHit()) {
            $this->pipeline = unserialize($item->get());
            return $this->pipeline;
        }

        // Entraînement (une seule fois, ~50-100ms)
        [$samples, $labels] = $this->buildDataset();

        $pipeline = new Pipeline([
            new TokenCountVectorizer(new WordTokenizer(), new French()),
            new TfIdfTransformer(),
        ], new SVC(Kernel::LINEAR, 1.0, 3, 0.0, 0.0, 0.001, 50, 1.0, true));

        $pipeline->train($samples, $labels);

        $item->set(serialize($pipeline));
        $this->cache->save($item);

        $this->pipeline = $pipeline;
        return $this->pipeline;
    }

    // ══════════════════════════════════════════════════════════════════
    //  FALLBACK RÈGLES (ultra-rapide, prioritaire)
    // ══════════════════════════════════════════════════════════════════

    private function ruleBasedFallback(string $msg): ?string
    {
        // ⚠️ ORDRE CRITIQUE : du plus spécifique au plus général.
        // ANALYSER_PERFORMANCE doit précéder DISPONIBILITE car "performances"
        // contient des sous-chaînes qui pourraient matcher d'autres règles.
        $rules = [
            // AIDE (mots très courts ou incohérents, priorité haute)
            '/^(aide|help|ساعد|مساعدة|كيفاش|stat|asdf|klak|klok|qwerty|test|truc|machin|chose|comment ca marche|que peux.?tu|what can you)$/' => 'AIDE',
            // Pattern pour détecter les répétitions de caractères sans voyelles (gibberish fréquent)
            '/^[^aeiouy\s]{4,}$/' => 'AIDE',

            // ANALYSER_PERFORMANCE — AVANT DISPONIBILITE
            '/\b(performances?|statistiques?|scores?|bilan|classement general|tableau de bord|qui travaille le mieux|evolution des employes|اداء|احسن عامل)\b/' => 'ANALYSER_PERFORMANCE',

            // COMPARER_TOP3
            '/\b(top\s*3|podium|comparer?|meilleur[s]?\s+employe|les\s*3\s+meilleurs|which.*best)\b/' => 'COMPARER_TOP3',

            // RECOMMANDER_EMPLOYE
            '/\b(recommande[r]?|conseille[r]?|assigne[r]?|suggest|recommend|من يقدر|اقترح)\b/' => 'RECOMMANDER_EMPLOYE',

            // RECHERCHER_COMPETENCE
            '/\b(competences?|expert en|sait (faire|utiliser)|maitris|connait|skills?|capable de|يعرف|expertise)\b/' => 'RECHERCHER_COMPETENCE',

            // DISPONIBILITE — en dernier, regex plus stricte pour éviter faux positifs
            '/\b(disponibilites?|libre[s]?|pas occupe|charge de travail|qui peut prendre|فاضي|وقتو فاضي)\b/' => 'DISPONIBILITE',
        ];

        foreach ($rules as $pattern => $intent) {
            if (preg_match($pattern . 'ui', $msg)) {
                return $intent;
            }
        }

        return null;
    }

    // ══════════════════════════════════════════════════════════════════
    //  DATASET TRILINGUE
    // ══════════════════════════════════════════════════════════════════

    private function buildDataset(): array
    {
        $data = [

            // ── RECOMMANDER_EMPLOYE ──────────────────────────────────
            'RECOMMANDER_EMPLOYE' => [
                // Français
                "qui me conseilles-tu pour la récolte",
                "j ai besoin de quelqu un pour irriguer",
                "recommande moi un employe pour le tracteur",
                "qui est le meilleur pour la taille",
                "trouve moi un expert en fertilisation",
                "donne moi un bon ouvrier pour cette tache",
                "qui peut faire la plantation",
                "qui est capable de conduire le tracteur",
                "suggere moi quelqu un pour arroser",
                "assigne un employe a la tache numero 3",
                "quel employe pour la serre",
                "je cherche quelqu un pour entretien",
                // Anglais
                "who should i assign to this task",
                "recommend an employee for irrigation",
                "who is best for harvest",
                "suggest someone for driving the tractor",
                "which employee can do planting",
                "find me a worker for fertilization",
                "who can handle the greenhouse",
                "best employee for task 5",
                // Franc-arabe tunisien
                "chkoun najem naayen bech yaamel",
                "min ynajjem yaamel fi tache",
                "chkoun l9a yaamel el hssed",
                "propose moi quelqu un bech ysakki",
                "chkoun l ahsen bech yaamel",
            ],

            // ── COMPARER_TOP3 ────────────────────────────────────────
            'COMPARER_TOP3' => [
                // Français
                "compare les employes pour cette tache",
                "donne moi le top 3",
                "qui sont les 3 meilleurs",
                "classement des employes pour irrigation",
                "je veux voir le podium",
                "compare les profils des travailleurs",
                "les meilleurs ouvriers pour recolte",
                "peux tu me donner le classement",
                "qui sont les candidats ideaux",
                "montre moi les trois premiers",
                // Anglais
                "compare top employees for this task",
                "show me the top 3 workers",
                "who are the best 3 for planting",
                "give me a ranking of employees",
                "compare profiles for harvest",
                // Franc-arabe
                "chkoun lkber 3",
                "aaytilni top 3 aamalin",
                "compare aamalin bech el hssed",
            ],

            // ── RECHERCHER_COMPETENCE ────────────────────────────────
            'RECHERCHER_COMPETENCE' => [
                // Français
                "qui sait utiliser un tracteur",
                "qui maitrise la recolte",
                "qui connait bien l irrigation",
                "quels sont les experts en machinerie",
                "employe avec la competence serre",
                "trouver un jardinier qui sait planter",
                "qui a l habitude de la fertilisation",
                "quelqu un qui connait la taille",
                "cherche un employe avec experience en entretien",
                "qui est qualifie pour la conduite",
                // Anglais
                "who knows how to use a tractor",
                "find someone with irrigation skills",
                "who has experience in harvesting",
                "which worker knows greenhouse management",
                "employee skilled in fertilization",
                // Franc-arabe
                "chkoun yaaref ysakki",
                "chkoun andu khibra fi tracteur",
                "chkoun yaaref el hssed",
            ],

            // ── ANALYSER_PERFORMANCE ─────────────────────────────────
            'ANALYSER_PERFORMANCE' => [
                // Français
                "analyse les performances des employes",
                "qui a les meilleures statistiques",
                "classement general des ouvriers",
                "qui est le plus rapide et efficace",
                "tableau des performances globales",
                "montre moi les scores de chacun",
                "qui travaille le mieux",
                "evolution des employes de la ferme",
                "quelles sont les stats generales",
                "bilan de performance",
                "qui a le meilleur taux de reussite",
                "voir les performances",
                // Anglais
                "show me employee performance",
                "who has the best stats",
                "performance analysis",
                "overall ranking of workers",
                "who performs best",
                "show scores for each employee",
                // Arabe / Franc-arabe
                "chkoun lbest fi khedma",
                "aaytilni statistiques laamalin",
                "من هو الأفضل أداءً",
                "أعطني إحصائيات الموظفين",
            ],

            // ── DISPONIBILITE ────────────────────────────────────────
            'DISPONIBILITE' => [
                // Français
                "qui est libre aujourd hui",
                "qui est disponible maintenant",
                "qui n est pas occupe",
                "donne moi les employes libres",
                "quelle est la charge de travail globale",
                "qui peut prendre une tache supplementaire",
                "qui a du temps libre",
                "voir les disponibilites des employes",
                "est ce que ali est libre",
                "savoir qui est libre pour travailler",
                "qui est dispo en ce moment",
                "qui peut commencer tout de suite",
                "employes disponibles aujourd hui",
                // Anglais
                "who is available today",
                "who is free right now",
                "show me available workers",
                "which employee has free time",
                "who can start immediately",
                "who is not busy",
                "check availability of employees",
                // Franc-arabe tunisien
                "chkoun fadi tawa",
                "chkoun ma andu tache",
                "chkoun ynajem yebda fi el khedma",
                "aaytilni aamalin fadin",
                "chkoun libre lhissa",
                // Arabe
                "من هو متاح الآن",
                "من ليس لديه مهام",
            ],

            // ── AIDE ─────────────────────────────────────────────────
            'AIDE' => [
                // Français
                "aide moi",
                "comment ca marche",
                "que peux tu faire",
                "je ne comprends pas",
                "quelles sont tes fonctionnalites",
                "montre moi ce que tu sais faire",
                "guide moi",
                "tu peux faire quoi",
                "expliquer moi le chatbot",
                "mode d emploi",
                // Anglais
                "help me",
                "how does this work",
                "what can you do",
                "show me your features",
                "i need help",
                "explain how to use this",
                // Franc-arabe
                "aaounni",
                "kifech nkhdem fik",
                "chnou taref tamel",
            ],

            // ── UNKNOWN (Noise / Gibberish) ──────────────────────────
            'UNKNOWN' => [
                "klak", "klok", "asdf", "qwerty", "test", "blabla", "truc",
                "bidule", "machin", "chose", "xyz", "12345", "bonjour",
                "salut", "hey", "hello", "ca va", "wesh", "labess",
                "chourou", "ghj", "dfgh", "vvvv", "nnnn", "mmmm",
                "lorem ipsum", "dolor sit amet", "consectetur", "adipiscing",
                "lundi", "mardi", "mercredi", "semaine", "parcelle",
                "vache", "mouton", "olivier", "tomate", "pomme de terre"
            ],
        ];

        $samples = [];
        $labels  = [];

        foreach ($data as $label => $phrases) {
            foreach ($phrases as $phrase) {
                $samples[] = $this->normalize($phrase);
                $labels[]  = $label;
            }
        }

        return [$samples, $labels];
    }

    // ══════════════════════════════════════════════════════════════════
    //  UTILITAIRES
    // ══════════════════════════════════════════════════════════════════

    /**
     * Normalise un message pour le ML :
     *  - Minuscules
     *  - Suppression ponctuation
     *  - Suppression diacritiques latins (é→e, à→a…)
     *  - Conservation des caractères arabes
     */
    public function normalize(string $message): string
    {
        $msg = strtolower(trim($message));
        // Suppression ponctuation sauf apostrophe et tiret
        $msg = preg_replace('/[?!.,;:()\[\]{}«»""\'\/\\\\]/', ' ', $msg ?? '');
        // Normalisation diacritiques latins uniquement
        $msg = preg_replace_callback(
            '/\p{L}/u',
            static function (array $m): string {
                $char = $m[0];
                // Conserver les caractères arabes et non-ASCII > 0x500
                if (preg_match('/[\x{0600}-\x{06FF}]/u', $char)) {
                    return $char;
                }
                $normalized = \Normalizer::normalize($char, \Normalizer::FORM_D);
                return preg_replace('/\p{Mn}/u', '', $normalized ?? $char) ?? $char;
            },
            $msg
        ) ?? $msg;
        // Espaces multiples
        return preg_replace('/\s+/', ' ', $msg) ?? $msg;
    }
}