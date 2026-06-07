<?php

namespace App\Service\Evenement;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates an event description via Groq (LLaMA 3.1)
 * and fetches an image via Unsplash — ported from the Java GeminiAIEventService.
 */
class GeminiAIEventService
{
    private const GROQ_URL     = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL   = 'llama-3.1-8b-instant';

    public function __construct(
        private HttpClientInterface  $httpClient,
        private UnsplashImageService $unsplashService,
        private LoggerInterface      $logger
    ) {}

    // ── Public API ───────────────────────────────────────────────────────────

    /**
     * Generate description + download an Unsplash image in one call.
     * Returns ['description' => string, 'imagePath' => string|null]
     *
     * @return array{description: string, imagePath: string|null}
     */
    public function genererEvenementComplet(string $titre, string $type, string $lieu): array
    {
        return [
            'description' => $this->genererDescription($titre, $type, $lieu),
            'imagePath'   => $this->genererImage($type),
        ];
    }

    public function genererDescription(string $titre, string $type, string $lieu): string
    {
        $prompt = $this->construirePrompt($titre, $type, $lieu);

        try {
            $response = $this->httpClient->request('POST', self::GROQ_URL, [
                'headers' => [
                    'Authorization' => 'Bearer ' . ($_ENV['GROQ_API_KEY'] ?? ''),
                    'Content-Type'  => 'application/json',
                ],
                'json' => [
                    'model'      => self::GROQ_MODEL,
                    'messages'   => [['role' => 'user', 'content' => $prompt]],
                    'max_tokens' => 300,
                ],
            ]);

            $data = $response->toArray();
            $text = $data['choices'][0]['message']['content'] ?? null;

            if ($text) {
                $this->logger->info('Groq description generated successfully.');
                return trim($text);
            }

        } catch (\Throwable $e) {
            $this->logger->error('Groq API error: {msg}', ['msg' => $e->getMessage()]);
        }

        // Fallback to local template
        return $this->genererDescriptionFallback($titre, $type, $lieu);
    }

    public function genererImage(string $type): ?string
    {
        return $this->unsplashService->rechercherImage($type);
    }

    // ── Private helpers ──────────────────────────────────────────────────────

    private function construirePrompt(string $titre, string $type, string $lieu): string
    {
        $typeDescription = match ($type) {
            'FOIRE'      => 'une foire agricole',
            'FORMATION'  => 'une formation agricole professionnelle',
            'CONFERENCE' => 'une conférence agricole',
            'ATELIER'    => 'un atelier pratique agricole',
            default      => 'un événement agricole',
        };

        return <<<PROMPT
Écris une description professionnelle et engageante de 3 à 4 phrases pour {$typeDescription}
intitulé "{$titre}" qui se tiendra à {$lieu} en Tunisie.

La description doit:
- Être en français professionnel
- Mettre en avant les bénéfices concrets pour les agriculteurs tunisiens
- Être motivante et donner envie de participer
- Mentionner l'aspect pratique et applicable
- Ne PAS inclure de date ni d'horaire

Écris uniquement la description, sans titre ni introduction.
PROMPT;
    }

    private function genererDescriptionFallback(string $titre, string $type, string $lieu): string
    {
        return match ($type) {
            'FOIRE' => sprintf(
                'Rejoignez-nous à %s pour %s, un événement incontournable qui rassemble agriculteurs, '
                . 'fournisseurs et experts du secteur agricole. Découvrez les dernières innovations, '
                . 'échangez avec des professionnels et explorez de nouvelles opportunités pour votre exploitation. '
                . 'Une occasion unique de développer votre réseau et d\'accéder à des solutions adaptées au contexte tunisien.',
                $lieu, $titre
            ),
            'FORMATION' => sprintf(
                'Cette formation pratique à %s vous permettra de maîtriser les techniques essentielles pour %s. '
                . 'Bénéficiez de l\'expertise de formateurs qualifiés et d\'ateliers pratiques adaptés aux réalités '
                . 'de l\'agriculture tunisienne. Repartez avec des compétences concrètes et directement applicables.',
                $lieu, $titre
            ),
            'CONFERENCE' => sprintf(
                'Participez à %s, une conférence qui réunit les acteurs clés du secteur agricole à %s. '
                . 'Au programme : présentations d\'experts, études de cas et débats sur les enjeux actuels. '
                . 'Une opportunité d\'apprentissage et de réflexion sur l\'avenir de l\'agriculture en Tunisie.',
                $titre, $lieu
            ),
            'ATELIER' => sprintf(
                'Cet atelier pratique à %s vous offre une expérience hands-on sur %s. '
                . 'Travaillez en petits groupes avec des encadrants expérimentés et pratiquez sur le terrain. '
                . 'Format interactif qui garantit une assimilation rapide des techniques.',
                $lieu, $titre
            ),
            default => sprintf(
                'Découvrez %s, un événement agricole à %s conçu pour vous accompagner dans le développement '
                . 'de vos activités. Programme riche en contenu pratique et en rencontres professionnelles.',
                $titre, $lieu
            ),
        };
    }
}
