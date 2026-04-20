<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Extension Twig — Filtres de formatage financier ARDHI
 *
 * PROBLÈME RÉSOLU :
 *   number_format(0, '.', '&#8239;') dans Twig → Twig échappe & → &amp;
 *   → le navigateur affiche "11&#8239;492" en texte brut au lieu de "11 492"
 *
 * SOLUTION :
 *   Filtre |tnd qui utilise le vrai caractère Unicode U+202F directement
 *   en PHP, sans passer par une entité HTML → pas d'échappement possible.
 *
 * USAGE dans Twig :
 *   {{ 11492 | tnd }}           → "11 492"
 *   {{ 11492.500 | tnd(3) }}    → "11 492.500"
 *   {{ montant | tnd(0, true) }} → "11 492 TND"
 */
class ArdhiFormatExtension extends AbstractExtension
{
    // U+202F = Narrow No-Break Space (standard typographie française/tunisienne)
    private const NNBSP = "\u{202F}";

    public function getFilters(): array
    {
        return [
            // Formatage montant TND
            new TwigFilter('tnd',   [$this, 'formatTnd'],   ['is_safe' => ['html']]),
            // Formatage nombre générique avec séparateur milliers
            new TwigFilter('num',   [$this, 'formatNum'],   ['is_safe' => ['html']]),
            // Formatage pourcentage
            new TwigFilter('pct',   [$this, 'formatPct'],   ['is_safe' => ['html']]),
        ];
    }

    /**
     * Formate un montant en TND.
     *
     * {{ 11492.750 | tnd }}        → "11 492"
     * {{ 11492.750 | tnd(3) }}     → "11 492.750"
     * {{ 11492.750 | tnd(0, true)}} → "11 492 TND"
     */
    public function formatTnd(
        float|int|string|null $value,
        int  $decimals    = 0,
        bool $showUnit    = false,
        bool $showSign    = false
    ): string {
        if ($value === null || $value === '') {
            return '—';
        }

        $n     = (float) $value;
        $sign  = '';

        if ($showSign && $n > 0) $sign = '+';

        // number_format avec le vrai caractère Unicode (pas une entité HTML)
        $formatted = number_format(abs($n), $decimals, '.', self::NNBSP);

        $result = $sign . ($n < 0 ? '-' : '') . $formatted;

        if ($showUnit) {
            $result .= "\u{202F}TND";
        }

        return $result;
    }

    /**
     * Formate un nombre avec séparateur de milliers.
     *
     * {{ 1234567 | num }}   → "1 234 567"
     * {{ 1234.5  | num(1) }} → "1 234.5"
     */
    public function formatNum(
        float|int|string|null $value,
        int $decimals = 0
    ): string {
        if ($value === null || $value === '') {
            return '—';
        }

        return number_format((float) $value, $decimals, '.', self::NNBSP);
    }

    /**
     * Formate un pourcentage.
     *
     * {{ 9.18 | pct }}     → "9.18%"
     * {{ 16.57 | pct(1) }} → "16.6%"
     */
    public function formatPct(
        float|int|string|null $value,
        int $decimals = 2
    ): string {
        if ($value === null) return '—';

        return number_format((float) $value, $decimals, '.', '') . "\u{202F}%";
    }
}