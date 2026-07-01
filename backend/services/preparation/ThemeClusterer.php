<?php

declare(strict_types=1);

namespace app\services\preparation;

/**
 * Clusters one language's prepared keywords into themed ad groups.
 *
 * Deliberately simple, deterministic clustering: count how often each meaningful token appears
 * across the language's keywords, then put every keyword in the ad group named after the
 * highest-frequency token it contains (ties broken alphabetically). Keywords that share a dominant
 * topic word — "website", "builder", "loja", "boutique" — land together. A small multilingual
 * stopword list and bare numbers are ignored, so a preposition never becomes a theme. Themes left
 * with a single keyword collapse into a "general" bucket so the preview isn't a wall of
 * one-keyword groups.
 *
 * This is a heuristic, documented as such (docs/PLAN.md). A smarter clusterer (embeddings or an
 * editable taxonomy) is a later refinement; the point here is an explainable, reproducible grouping.
 */
final class ThemeClusterer
{
    public const GENERAL_KEY = 'general';
    public const GENERAL_LABEL = 'General';

    /** A theme needs at least this many keywords to stand on its own; smaller ones fold into general. */
    private const MIN_GROUP = 2;

    /**
     * Function words across the six languages we handle (en/de/es/fr/it/pt). A term's theme must be
     * a content word, so these are excluded from token counting. Kept to common articles /
     * prepositions / conjunctions — enough to stop a stopword winning as a theme, not a full
     * linguistic filter.
     *
     * @var array<string, true>
     */
    private const STOPWORDS = [
        // en
        'a' => true, 'an' => true, 'and' => true, 'are' => true, 'as' => true, 'at' => true,
        'be' => true, 'but' => true, 'by' => true, 'for' => true, 'from' => true, 'how' => true,
        'in' => true, 'is' => true, 'it' => true, 'of' => true, 'on' => true, 'or' => true,
        'the' => true, 'this' => true, 'to' => true, 'with' => true, 'your' => true, 'my' => true,
        // de
        'der' => true, 'die' => true, 'das' => true, 'den' => true, 'dem' => true, 'ein' => true,
        'eine' => true, 'einen' => true, 'und' => true, 'oder' => true, 'für' => true, 'mit' => true,
        'von' => true, 'zu' => true, 'im' => true, 'auf' => true, 'ist' => true, 'als' => true,
        // es
        'el' => true, 'la' => true, 'los' => true, 'las' => true, 'un' => true, 'una' => true,
        'y' => true, 'o' => true, 'de' => true, 'del' => true, 'en' => true, 'para' => true,
        'con' => true, 'por' => true, 'es' => true,
        // fr
        'le' => true, 'les' => true, 'une' => true, 'des' => true, 'du' => true, 'et' => true,
        'ou' => true, 'pour' => true, 'avec' => true, 'sur' => true, 'au' => true, 'aux' => true,
        // it
        'il' => true, 'lo' => true, 'gli' => true, 'di' => true, 'e' => true, 'da' => true,
        'su' => true, 'è' => true, 'per' => true, 'con' => true,
        // pt
        'os' => true, 'as' => true, 'uma' => true, 'do' => true, 'da' => true, 'em' => true,
        'no' => true, 'na' => true, 'ou' => true, 'para' => true, 'com' => true,
    ];

    /**
     * Assign each keyword to a theme.
     *
     * @param array<int, string> $terms keyword id => normalized term
     * @return array<int, array{theme_key: string, theme: string}>
     */
    public function assign(array $terms): array
    {
        // Token frequency across the language, and the token list per keyword (cached).
        $freq = [];
        $tokensById = [];
        foreach ($terms as $id => $term) {
            $tokens = $this->meaningfulTokens($term);
            $tokensById[$id] = $tokens;
            foreach (array_unique($tokens) as $token) {
                $freq[$token] = ($freq[$token] ?? 0) + 1;
            }
        }

        // Pass 1: each keyword takes the highest-frequency token it contains (ties → alphabetical).
        $assigned = [];
        foreach ($terms as $id => $term) {
            $best = null;
            $bestFreq = -1;
            foreach ($tokensById[$id] as $token) {
                $f = $freq[$token];
                if ($f > $bestFreq || ($f === $bestFreq && ($best === null || strcmp($token, $best) < 0))) {
                    $best = $token;
                    $bestFreq = $f;
                }
            }
            $assigned[$id] = $best ?? self::GENERAL_KEY;
        }

        // Pass 2: fold single-keyword themes into the general bucket.
        $sizes = array_count_values($assigned);
        foreach ($assigned as $id => $key) {
            if ($key !== self::GENERAL_KEY && $sizes[$key] < self::MIN_GROUP) {
                $assigned[$id] = self::GENERAL_KEY;
            }
        }

        $out = [];
        foreach ($assigned as $id => $key) {
            $out[$id] = [
                'theme_key' => $key,
                'theme' => $key === self::GENERAL_KEY ? self::GENERAL_LABEL : $this->label($key),
            ];
        }

        return $out;
    }

    /**
     * The content tokens of a normalized term: split on spaces, drop stopwords, bare numbers, and
     * single characters. Returns lowercased tokens (the term is already normalized).
     *
     * @return string[]
     */
    private function meaningfulTokens(string $term): array
    {
        $tokens = [];
        foreach (explode(' ', $term) as $token) {
            if ($token === '' || mb_strlen($token, 'UTF-8') < 2) {
                continue;
            }
            if (isset(self::STOPWORDS[$token])) {
                continue;
            }
            if (preg_match('/\p{L}/u', $token) !== 1) {
                continue; // digits / symbols only — not a topic word
            }
            $tokens[] = $token;
        }

        return $tokens;
    }

    /** Human label for a theme key: first letter upper-cased, rest as-is (UTF-8 aware). */
    private function label(string $key): string
    {
        return mb_convert_case(mb_substr($key, 0, 1, 'UTF-8'), MB_CASE_UPPER, 'UTF-8')
            . mb_substr($key, 1, null, 'UTF-8');
    }
}
