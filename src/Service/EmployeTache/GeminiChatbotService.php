<?php

namespace App\Service\EmployeTache;

use App\Repository\EmployeTache\EmployeRepository;
use App\Repository\EmployeTache\TacheRepository;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * 🤖 GeminiChatbotService — Fallback intelligent via Google Gemini AI
 *
 * Déclenché uniquement quand le pipeline ML local retourne UNKNOWN.
 * Fournit des réponses en langage naturel contextualisées à la ferme.
 */
class GeminiChatbotService
{
    private const GEMINI_API_URL = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash-lite:generateContent';
    private const MAX_TOKENS      = 512;
    private const TIMEOUT         = 8.0; // secondes

    public function __construct(
        private HttpClientInterface  $httpClient,
        private EmployeRepository    $employeRepository,
        private TacheRepository      $tacheRepository,
        private TranslatorInterface  $translator,
        private MeteoService         $meteoService,
        private string               $geminiApiKey,
    ) {}

    // ══════════════════════════════════════════════════════════════════
    //  POINT D'ENTRÉE
    // ══════════════════════════════════════════════════════════════════

    /**
     * Génère une réponse Gemini pour un message libre non reconnu par le ML.
     *
     * @param string $message        Message original de l'utilisateur
     * @param int    $idAgriculteur  ID de l'agriculteur connecté
     * @param string $lang           Langue détectée ('fr', 'en', 'ar')
     * @param string|null $lastIntent Dernière intention (contexte conversationnel)
     */
    public function generateResponse(
        string  $message,
        int     $idAgriculteur,
        string  $lang = 'fr',
        ?string $lastIntent = null
    ): ChatbotResponse {
        $response = new ChatbotResponse();
        $response->messageUtilisateur = $message;
        $response->intention = 'GEMINI_FALLBACK';

        if (empty($this->geminiApiKey)) {
            $response->reponse = $this->translator->trans('chatbot.understanding.no_key', [], null, $lang);
            return $response;
        }

        try {
            $prompt = $this->buildPrompt($message, $idAgriculteur, $lang, $lastIntent);
            $answer = $this->callGeminiApi($prompt);
            $response->reponse = $answer ?: $this->translator->trans('chatbot.understanding.no_key', [], null, $lang);
        } catch (\Throwable $e) {
            // Fallback gracieux si l'API Gemini échoue
            $response->reponse = $this->getFallbackMessage($lang);
        }

        return $response;
    }

    // ══════════════════════════════════════════════════════════════════
    //  CONSTRUCTION DU PROMPT
    // ══════════════════════════════════════════════════════════════════

    private function buildPrompt(
        string  $message,
        int     $idAgriculteur,
        string  $lang,
        ?string $lastIntent
    ): string {
        $langLabel = match ($lang) {
            'ar'    => 'arabe (dialecte tunisien)',
            'en'    => 'anglais',
            default => 'français',
        };

        // Contexte : employés actifs
        $employes = $this->employeRepository->findActifsByAgriculteur($idAgriculteur);
        $empList  = '';
        foreach (array_slice($employes, 0, 10) as $emp) {
            $nb = $this->tacheRepository->countTachesActivesParEmploye((int) $emp->getId(), $idAgriculteur);
            $empList .= sprintf(
                "- %s %s (%s) — %d tâche(s) en cours\n",
                $emp->getPrenom(),
                $emp->getNom(),
                $emp->getPoste() ?? 'employé',
                $nb
            );
        }
        if (empty($empList)) {
            $empList = "- Aucun employé actif enregistré\n";
        }

        // Contexte : tâches actives
        $taches = $this->tacheRepository->findBy(['idAgriculteur' => $idAgriculteur]);
        $statuts_termines = ['terminé', 'terminee', 'validé', 'validee', 'annulé', 'annulee'];
        $actives = array_filter(
            $taches,
            fn($t) => !in_array(strtolower($t->getStatut()), $statuts_termines, true)
        );
        $tacheList = '';
        foreach (array_slice($actives, 0, 8) as $t) {
            $tacheList .= sprintf("- #%d : %s (%s)\n", $t->getId(), $t->getTitre(), $t->getStatut());
        }
        if (empty($tacheList)) {
            $tacheList = "- Aucune tâche active\n";
        }

        // Contexte : météo actuelle
        $w = $this->meteoService->getCurrentWeather();
        $meteoContext = "Conditions météo actuelles : Impossible de récupérer les données.";
        if ($w->isAvailable()) {
            $meteoContext = sprintf(
                "Météo actuelle à %s : %d°C, %s, Humidité %d%%, Vent %d km/h.",
                $w->getCityName(),
                round($w->getTemperature()),
                $w->getDescription(),
                $w->getHumidity(),
                round($w->getWindSpeed())
            );
            $advice = $this->meteoService->genererRecommandationsGenerales($w);
            if (!empty($advice)) {
                $meteoContext .= " Conseils : " . implode(' ', array_map(fn($r) => $r->message, $advice));
            }
        }

        // Contexte conversationnel
        $contextInfo = '';
        if ($lastIntent) {
            $intentLabels = [
                'RECOMMANDER_EMPLOYE'   => 'recommandation d\'employé',
                'ANALYSER_PERFORMANCE'  => 'analyse de performances',
                'DISPONIBILITE'         => 'disponibilités des employés',
                'COMPARER_TOP3'         => 'comparaison du top 3',
                'RECHERCHER_COMPETENCE' => 'recherche de compétences',
            ];
            $last = $intentLabels[$lastIntent] ?? $lastIntent;
            $contextInfo = "\nContexte : la dernière action de l'utilisateur était une $last.\n";
        }

        return <<<PROMPT
Tu es **SmartFarm RH**, l'assistant intelligent d'une plateforme agricole tunisienne (Ardhi).
Tu aides les agriculteurs à gérer leurs employés et leurs tâches agricoles.
Réponds en **{$langLabel}**, de manière concise, amicale et professionnelle (maximum 4 phrases).
N'invente pas de données — utilise uniquement les informations ci-dessous.
Si la question est hors sujet (recettes, politique, etc.), réponds poliment que tu es spécialisé en gestion agricole.
{$contextInfo}
**Météo et environnement :**
{$meteoContext}

**Employés actifs de la ferme :**
{$empList}
**Tâches agricoles en cours :**
{$tacheList}

**Question de l'agriculteur :** {$message}

Réponds directement et utilement. Si tu peux suggérer une action (recommander un employé, voir les disponibilités, analyser les performances, conseil météo), mentionne-la.
PROMPT;
    }

    // ══════════════════════════════════════════════════════════════════
    //  APPEL API GEMINI
    // ══════════════════════════════════════════════════════════════════

    private function callGeminiApi(string $prompt): string
    {
        $url = self::GEMINI_API_URL . '?key=' . $this->geminiApiKey;

        $response = $this->httpClient->request('POST', $url, [
            'timeout' => self::TIMEOUT,
            'json'    => [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                        ],
                    ],
                ],
                'generationConfig' => [
                    'maxOutputTokens' => self::MAX_TOKENS,
                    'temperature'     => 0.7,
                    'topP'            => 0.9,
                ],
                'safetySettings' => [
                    [
                        'category'  => 'HARM_CATEGORY_HARASSMENT',
                        'threshold' => 'BLOCK_ONLY_HIGH',
                    ],
                    [
                        'category'  => 'HARM_CATEGORY_HATE_SPEECH',
                        'threshold' => 'BLOCK_ONLY_HIGH',
                    ],
                ],
            ],
        ]);

        $data = $response->toArray(false); // false = pas d'exception sur erreur HTTP

        // Lecture de la réponse Gemini
        return $data['candidates'][0]['content']['parts'][0]['text']
            ?? $data['error']['message']
            ?? '';
    }

    // ══════════════════════════════════════════════════════════════════
    //  UTILITAIRES
    // ══════════════════════════════════════════════════════════════════

    private function getFallbackMessage(string $lang): string
    {
        return match ($lang) {
            'ar'    => '⚠️ لم أتمكن من معالجة طلبك الآن. يرجى المحاولة مجدداً أو استخدام الأزرار السريعة.',
            'en'    => '⚠️ I couldn\'t process your request right now. Please try again or use the quick action buttons.',
            default => '⚠️ Je n\'ai pas pu traiter votre demande pour le moment. Réessayez ou utilisez les boutons d\'action rapide.',
        };
    }
}