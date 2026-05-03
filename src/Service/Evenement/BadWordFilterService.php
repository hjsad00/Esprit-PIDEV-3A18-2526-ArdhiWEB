<?php

namespace App\Service\Evenement;

class BadWordFilterService
{
    /**
     * @var list<string>
     */
    private array $badWords = [
        // French common bad words and vulgarities (non-exhaustive list)
        'connard', 'connasse', 'con', 'salaud', 'salope', 'putain', 'merde', 'chier',
        'enculé', 'enculée', 'encule', 'salaud', 'débile', 'taré', 'tarée', 'débiles',
        'imbécile', 'imbecile', 'débile', 'débiles', 'connards', 'connasses', 'salauds',
        'salopes', 'putains', 'connerie', 'conneries', 'salopperie', 'salopperies',
        'merdique', 'merdiques', 'pourri', 'pourrie', 'pourris', 'pourries',
        'salaud', 'salauds', 'salope', 'salopes', 'pute', 'putes',
        // English common bad words
        'fuck', 'fucking', 'shit', 'damn', 'bitch', 'ass', 'asshole', 'bastard',
        'crap', 'hell', 'piss', 'suck', 'dick', 'cock', 'pussy',
        // Add more as needed
    ];

    /**
     * Check if text contains any bad words
     * Returns array of bad words found, or empty array if none
     *
     * @return list<string>
     */
    public function hasBadWords(string $text): array
    {
        $textLower = mb_strtolower($text, 'UTF-8');
        $foundBadWords = [];

        foreach ($this->badWords as $badWord) {
            $badWordLower = mb_strtolower($badWord, 'UTF-8');
            if (preg_match('/\b' . preg_quote($badWordLower, '/') . '\b/u', $textLower)) {
                if (!in_array($badWord, $foundBadWords, true)) {
                    $foundBadWords[] = $badWord;
                }
            }
        }

        return $foundBadWords;
    }

    /**
     * Check if text has bad words (simple boolean check)
     */
    public function containsBadWords(string $text): bool
    {
        return count($this->hasBadWords($text)) > 0;
    }

    /**
     * Replace bad words with asterisks
     */
    public function filterBadWords(string $text): string
    {
        foreach ($this->badWords as $badWord) {
            $badWordLower = mb_strtolower($badWord, 'UTF-8');
            $replacement = str_repeat('*', strlen($badWordLower));
            $filteredText = preg_replace('/\b' . preg_quote($badWordLower, '/') . '\b/iu', $replacement, $text);
            if ($filteredText !== null) {
                $text = $filteredText;
            }
        }

        return $text;
    }

    /**
     * Get validation message for bad words
     *
     * @param list<string> $badWords
     */
    public function getErrorMessage(array $badWords): string
    {
        $words = implode(', ', array_map(fn($w) => "\"$w\"", $badWords));
        return sprintf('Votre texte contient des mots non autorisés : %s. Veuillez les retirer.', $words);
    }

    /**
     * Add custom bad words to the filter
     *
     * @param list<string> $words
     */
    public function addBadWords(array $words): void
    {
        $this->badWords = array_merge($this->badWords, $words);
        $this->badWords = array_values(array_unique($this->badWords));
    }
}
