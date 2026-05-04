<?php

namespace App\Service\Marketplace;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Analyse les messages utilisateur via Ollama et renvoie une intention normalisee.
 */
class OllamaMarketplaceIntentService
{
    private const OLLAMA_URL = 'http://localhost:11434/api/generate';
    private const MODEL = 'mistral';
    private const TIMEOUT_SECONDS = 30;

    private const ALLOWED_INTENTIONS = [
        'achat',
        'disponibilite',
        'vider_panier',
        'supprimer_produit',
        'filtrer',
        'salutation',
        'remerciement',
        'hors_sujet',
    ];

    private const ALLOWED_CRITERES = ['prix_asc', 'prix_desc', 'avis', 'avis_asc', 'avis_desc'];

    private const SYSTEM_PROMPT = <<<'PROMPT'
Tu es un assistant pour un marketplace agricole.
Analyse le message utilisateur et reponds UNIQUEMENT avec ce JSON (rien d'autre) :
{
  "intention": "achat" | "disponibilite" | "vider_panier" | "supprimer_produit" | "filtrer" | "salutation" | "remerciement" | "hors_sujet",
  "produits": [{"nom": "...", "quantite": N}],
  "critere": "prix_asc" | "prix_desc" | "avis_desc" | "avis_asc" | null,
  "recherche": "..." | null,
  "categorie": "..." | null,
  "prixMin": N | null,
  "prixMax": N | null
}

Regles importantes :
- Si l'utilisateur veut acheter des produits -> intention = "achat"
- Si l'utilisateur demande si un produit est disponible -> intention = "disponibilite"
- Si l'utilisateur veut vider son panier -> intention = "vider_panier"
- Si l'utilisateur veut supprimer un article precis du panier -> intention = "supprimer_produit"
- Si l'utilisateur veut voir/chercher/filtrer des produits -> intention = "filtrer"
- Si l'utilisateur dit bonjour ou au revoir -> intention = "salutation"
- Si l'utilisateur dit merci -> intention = "remerciement"
- Si le message est hors contexte marketplace -> intention = "hors_sujet"

Regles pour "critere" (s'applique aussi a "achat") :
- critere = "prix_asc" (moins cher d'abord)
- critere = "prix_desc" (plus cher d'abord)
- critere = "avis_desc" (mieux note / meilleur avis)
- critere = "avis_asc" (moins bien note)
- critere = null (pas de preference)

Regles pour "filtrer" :
- recherche = mot-cle libre ou null
- categorie = categorie (Fruits, Legumes, Cereales...) ou null
- prixMin = nombre ou null
- prixMax = nombre ou null

Toujours renvoyer un JSON valide.
Ne renvoie aucun markdown, aucun texte hors JSON.
PROMPT;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array{
     *   intention:string,
     *   produits:array<int,array{nom:string,quantite:int}>,
     *   critere:?string,
     *   recherche:?string,
     *   categorie:?string,
     *   prixMin:?float,
     *   prixMax:?float
     * }
     */
    public function analyser(string $messageUtilisateur): array
    {
        $messageUtilisateur = trim($messageUtilisateur);
        if ($messageUtilisateur == '') {
            return $this->buildFallbackIntent('hors_sujet');
        }

        try {
            $prompt = self::SYSTEM_PROMPT . "\n\nMessage utilisateur : " . $messageUtilisateur;

            $response = $this->httpClient->request('POST', self::OLLAMA_URL, [
                'json' => [
                    'model' => self::MODEL,
                    'prompt' => $prompt,
                    'stream' => false,
                    'format' => 'json',
                    'options' => [
                        'temperature' => 0,
                    ],
                ],
                'timeout' => self::TIMEOUT_SECONDS,
            ]);

            if ($response->getStatusCode() !== 200) {
                throw new \RuntimeException('Ollama HTTP ' . $response->getStatusCode());
            }

            $payload = $response->toArray(false);
            $rawText = (string) ($payload['response'] ?? '');
            $jsonText = $this->extractJson($rawText);

            try {
                $decoded = json_decode($jsonText, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                $decoded = null;
            }

            if (!is_array($decoded)) {
                return $this->buildHeuristicIntent($messageUtilisateur);
            }

            $normalizedIntent = $this->normalizeIntent($decoded);

            // Fallback heuristique si intention = hors_sujet
            if ($normalizedIntent['intention'] === 'hors_sujet') {
                $heuristic = $this->buildHeuristicIntent($messageUtilisateur);
                if ($heuristic['intention'] !== 'hors_sujet') {
                    return $heuristic;
                }
            }

            // Si l'IA a detecte une intention qui necessite des produits mais n'en a extrait aucun,
            // on complete avec l'extraction heuristique du message original.
            $intentionsNeedingProducts = ['achat', 'supprimer_produit', 'disponibilite'];
            if (
                in_array($normalizedIntent['intention'], $intentionsNeedingProducts, true)
                && $normalizedIntent['produits'] === []
            ) {
                $normalizedIntent['produits'] = $this->extractProduitFromMessage($messageUtilisateur);
            }

            return $normalizedIntent;
        } catch (\Throwable $e) {
            $this->logger->error('Ollama marketplace error: ' . $e->getMessage());
            return $this->buildHeuristicIntent($messageUtilisateur);
        }
    }

    private function extractJson(string $raw): string
    {
        $trimmed = trim($raw);
        if ($trimmed === '') {
            return '{}';
        }

        $fenceStart = strpos($trimmed, '```');
        if ($fenceStart !== false) {
            $trimmed = preg_replace('/```(?:json)?/i', '', $trimmed) ?? $trimmed;
            $trimmed = str_replace('```', '', $trimmed);
            $trimmed = trim($trimmed);
        }

        $first = strpos($trimmed, '{');
        $last = strrpos($trimmed, '}');
        if ($first === false || $last === false || $last <= $first) {
            return '{}';
        }

        return substr($trimmed, $first, $last - $first + 1);
    }

    /**
     * @param array<string,mixed> $decoded
     * @return array{
     *   intention:string,
     *   produits:array<int,array{nom:string,quantite:int}>,
     *   critere:?string,
     *   recherche:?string,
     *   categorie:?string,
     *   prixMin:?float,
     *   prixMax:?float
     * }
     */
    private function normalizeIntent(array $decoded): array
    {
        $intention = strtolower((string) ($decoded['intention'] ?? 'hors_sujet'));
        if (!in_array($intention, self::ALLOWED_INTENTIONS, true)) {
            $intention = 'hors_sujet';
        }

        $critere = $this->normalizeNullableString($decoded['critere'] ?? null);
        if ($critere !== null && !in_array($critere, self::ALLOWED_CRITERES, true)) {
            $critere = null;
        }

        $produits = [];
        $rawProduits = $decoded['produits'] ?? [];
        if (is_array($rawProduits)) {
            foreach ($rawProduits as $rawProduit) {
                if (!is_array($rawProduit)) {
                    continue;
                }

                $nom = trim((string) ($rawProduit['nom'] ?? ''));
                if ($nom === '') {
                    continue;
                }

                $quantite = (int) ($rawProduit['quantite'] ?? 1);
                if ($quantite < 1) {
                    $quantite = 1;
                }

                $produits[] = [
                    'nom' => $nom,
                    'quantite' => $quantite,
                ];
            }
        }

        $prixMin = $this->normalizeNullableFloat($decoded['prixMin'] ?? $decoded['prix_min'] ?? null);
        $prixMax = $this->normalizeNullableFloat($decoded['prixMax'] ?? $decoded['prix_max'] ?? null);

        if ($prixMin !== null && $prixMax !== null && $prixMax < $prixMin) {
            [$prixMin, $prixMax] = [$prixMax, $prixMin];
        }

        return [
            'intention' => $intention,
            'produits' => $produits,
            'critere' => $critere,
            'recherche' => $this->normalizeNullableString($decoded['recherche'] ?? null),
            'categorie' => $this->normalizeNullableString($decoded['categorie'] ?? null),
            'prixMin' => $prixMin,
            'prixMax' => $prixMax,
        ];
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '' || strtolower($normalized) === 'null') {
            return null;
        }

        return $normalized;
    }

    private function normalizeNullableFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && strtolower(trim($value)) === 'null') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        return (float) $value;
    }

    /**
     * @return array{
     *   intention:string,
     *   produits:array<int,array{nom:string,quantite:int}>,
     *   critere:?string,
     *   recherche:?string,
     *   categorie:?string,
     *   prixMin:?float,
     *   prixMax:?float
     * }
     */
    private function buildFallbackIntent(string $intention): array
    {
        $safeIntention = in_array($intention, self::ALLOWED_INTENTIONS, true) ? $intention : 'hors_sujet';

        return [
            'intention' => $safeIntention,
            'produits' => [],
            'critere' => null,
            'recherche' => null,
            'categorie' => null,
            'prixMin' => null,
            'prixMax' => null,
        ];
    }

    /**
     * Filet de securite local quand Ollama renvoie un JSON invalide ou hors contexte.
     *
     * @return array{
     *   intention:string,
     *   produits:array<int,array{nom:string,quantite:int}>,
     *   critere:?string,
     *   recherche:?string,
     *   categorie:?string,
     *   prixMin:?float,
     *   prixMax:?float
     * }
     */
    private function buildHeuristicIntent(string $message): array
    {
        $intent = $this->buildFallbackIntent('hors_sujet');
        $text = $this->normalizeForMatch($message);

        if (preg_match('/\b(merci|thank|thanks|chokran|merci beaucoup)\b/i', $text)) {
            $intent['intention'] = 'remerciement';
            return $intent;
        }

        if (preg_match('/\b(salut|bonjour|coucou|hello|bonsoir|bzj|slt)\b/i', $text)) {
            $intent['intention'] = 'salutation';
            return $intent;
        }

        if (str_contains($text, 'vider') && str_contains($text, 'panier')) {
            $intent['intention'] = 'vider_panier';
            return $intent;
        }

        $isDelete = str_contains($text, 'supprimer')
            || str_contains($text, 'supprime')
            || str_contains($text, 'retirer')
            || str_contains($text, 'retire')
            || str_contains($text, 'enlever')
            || str_contains($text, 'enleve');

        if ($isDelete && str_contains($text, 'panier')) {
            $intent['intention'] = 'supprimer_produit';
            $intent['produits'] = $this->extractProduitFromMessage($message);
            return $intent;
        }

        $isAchat = str_contains($text, 'ajouter')
            || str_contains($text, 'ajoute')
            || str_contains($text, 'acheter')
            || str_contains($text, 'achete')
            || str_contains($text, 'mettre')
            || str_contains($text, 'met ');

        if ($isAchat || str_contains($text, 'panier')) {
            $produits = $this->extractProduitFromMessage($message);
            if ($produits !== []) {
                $intent['intention'] = 'achat';
                $intent['produits'] = $produits;
                return $intent;
            }
        }

        if (str_contains($text, 'disponible') || str_contains($text, 'stock') || str_contains($text, 'disponibilite')) {
            $intent['intention'] = 'disponibilite';
            $intent['produits'] = $this->extractProduitFromMessage($message);
            return $intent;
        }

        $isFiltre = str_contains($text, 'filtrer')
            || str_contains($text, 'filtre')
            || str_contains($text, 'cherche')
            || str_contains($text, 'chercher')
            || str_contains($text, 'montre')
            || str_contains($text, 'affiche')
            || str_contains($text, 'categorie')
            || str_contains($text, 'prix')
            || str_contains($text, 'moins cher')
            || str_contains($text, 'plus cher');

        if ($isFiltre) {
            $intent['intention'] = 'filtrer';
            $intent['critere'] = $this->extractCritereFromMessage($text);
            $intent['categorie'] = $this->extractCategorieFromMessage($text);

            [$prixMin, $prixMax] = $this->extractPrixRangeFromMessage($text);
            $intent['prixMin'] = $prixMin;
            $intent['prixMax'] = $prixMax;

            $recherche = $this->extractRechercheFromMessage($message);
            if ($recherche !== null) {
                $intent['recherche'] = $recherche;
            }
        }

        return $intent;
    }

    private function normalizeForMatch(string $text): string
    {
        $text = trim($text);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        if ($ascii !== false) {
            $text = $ascii;
        }

        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9 ]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return trim($text);
    }

    /**
     * @return array<int,array{nom:string,quantite:int}>
     */
    private function extractProduitFromMessage(string $message): array
    {
        $normalized = $this->normalizeForMatch($message);
        if ($normalized === '') {
            return [];
        }

        $quantite = 1;
        if (preg_match('/\b(\d+)\b/', $normalized, $matches) === 1) {
            $quantite = max(1, (int) $matches[1]);
        }

        $clean = preg_replace('/\b\d+\b/', ' ', $normalized) ?? $normalized;
        $clean = preg_replace(
            '/\b(ajoute|ajouter|acheter|achete|mettre|met|supprime|supprimer|retire|retirer|enleve|enlever|panier|au|a|du|de|des|la|le|les|mon|ma|mes|un|une|produit|produits|stp|svp)\b/',
            ' ',
            $clean
        ) ?? $clean;
        $clean = preg_replace('/\s+/', ' ', $clean) ?? $clean;
        $clean = trim($clean);

        if ($clean === '' || strlen($clean) < 2) {
            return [];
        }

        return [[
            'nom' => $clean,
            'quantite' => $quantite,
        ]];
    }

    private function extractCritereFromMessage(string $normalizedText): ?string
    {
        if (str_contains($normalizedText, 'moins cher') || str_contains($normalizedText, 'prix croissant')) {
            return 'prix_asc';
        }

        if (str_contains($normalizedText, 'plus cher') || str_contains($normalizedText, 'prix decroissant')) {
            return 'prix_desc';
        }

        if (str_contains($normalizedText, 'moins note') || str_contains($normalizedText, 'moins bien note') || str_contains($normalizedText, 'pire avis')) {
            return 'avis_asc';
        }

        if (str_contains($normalizedText, 'mieux note') || str_contains($normalizedText, 'meilleur avis') || str_contains($normalizedText, 'plus note') || str_contains($normalizedText, 'meilleure note')) {
            return 'avis_desc';
        }

        return null;
    }

    private function extractCategorieFromMessage(string $normalizedText): ?string
    {
        $map = [
            'fruits' => 'Fruits',
            'fruit' => 'Fruits',
            'legumes' => 'Legumes',
            'legume' => 'Legumes',
            'cereales' => 'Cereales',
            'cereale' => 'Cereales',
        ];

        foreach ($map as $needle => $category) {
            if (str_contains($normalizedText, $needle)) {
                return $category;
            }
        }

        return null;
    }

    /**
     * @return array{0:?float,1:?float}
     */
    private function extractPrixRangeFromMessage(string $normalizedText): array
    {
        $prixMin = null;
        $prixMax = null;

        if (preg_match('/\bentre\s+(\d+(?:[.,]\d+)?)\s+et\s+(\d+(?:[.,]\d+)?)/', $normalizedText, $match) === 1) {
            $prixMin = (float) str_replace(',', '.', (string) $match[1]);
            $prixMax = (float) str_replace(',', '.', (string) $match[2]);
        }

        if ($prixMax === null && preg_match('/\bmoins de\s+(\d+(?:[.,]\d+)?)/', $normalizedText, $match) === 1) {
            $prixMax = (float) str_replace(',', '.', (string) $match[1]);
        }

        if ($prixMin === null && preg_match('/\bplus de\s+(\d+(?:[.,]\d+)?)/', $normalizedText, $match) === 1) {
            $prixMin = (float) str_replace(',', '.', (string) $match[1]);
        }

        if ($prixMin !== null && $prixMax !== null && $prixMax < $prixMin) {
            [$prixMin, $prixMax] = [$prixMax, $prixMin];
        }

        return [$prixMin, $prixMax];
    }

    private function extractRechercheFromMessage(string $message): ?string
    {
        $normalized = $this->normalizeForMatch($message);

        if (preg_match('/\b(cherche|chercher|recherche|rechercher|montre|affiche)\s+(.*)$/', $normalized, $match) !== 1) {
            return null;
        }

        $candidate = trim((string) $match[2]);
        $candidate = preg_replace('/\b(des|de|du|les|le|la|produit|produits|categorie)\b/', ' ', $candidate) ?? $candidate;
        $candidate = preg_replace('/\s+/', ' ', $candidate) ?? $candidate;
        $candidate = trim($candidate);

        if ($candidate === '' || strlen($candidate) < 2) {
            return null;
        }

        return $candidate;
    }
}
