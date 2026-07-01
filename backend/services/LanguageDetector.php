<?php

declare(strict_types=1);

namespace app\services;

/**
 * Lightweight language guess for the six markets we target (en, de, es, fr, pt, it).
 *
 * This is a deliberately simple heuristic — distinctive marker words plus a few
 * diacritic signals — used ONLY as a fallback for sources that ship no language
 * column (Search Console). Sources that carry an explicit language are trusted
 * directly and never go through here. Short ad-style queries are hard to classify,
 * so when nothing distinctive matches we default to English rather than guess.
 */
final class LanguageDetector
{
    public const DEFAULT = 'en';

    /** Distinctive marker words per language (domain: website builders). */
    private const MARKERS = [
        'de' => ['erstellen', 'webseite', 'homepage', 'baukasten', 'kostenlos', 'eigene', 'für', 'und', 'mit', 'seite'],
        'es' => ['crear', 'sitio', 'gratis', 'página', 'pagina', 'cómo', 'como', 'para', 'con', 'tienda', 'propia'],
        'fr' => ['créer', 'creer', 'gratuit', 'internet', 'pour', 'avec', 'comment', 'boutique', 'gratuitement', 'un'],
        'pt' => ['criar', 'grátis', 'gratis', 'próprio', 'proprio', 'como', 'para', 'com', 'loja', 'página', 'de'],
        'it' => ['creare', 'sito', 'gratis', 'pagina', 'come', 'per', 'con', 'negozio', 'proprio', 'gratuito'],
        'en' => ['website', 'builder', 'free', 'make', 'create', 'online', 'best', 'how', 'own', 'page'],
    ];

    /** Diacritic → language hints (a nudge, not a decision). */
    private const DIACRITICS = [
        'ß' => 'de', 'ä' => 'de', 'ö' => 'de', 'ü' => 'de',
        'ñ' => 'es', '¿' => 'es', '¡' => 'es',
        'ã' => 'pt', 'õ' => 'pt',
    ];

    public function detect(string $term): string
    {
        $lower = mb_strtolower($term, 'UTF-8');
        $tokens = preg_split('/\s+/u', trim($lower)) ?: [];
        $tokenSet = array_flip($tokens);

        $scores = array_fill_keys(array_keys(self::MARKERS), 0);

        foreach (self::MARKERS as $lang => $words) {
            foreach ($words as $word) {
                if (isset($tokenSet[$word])) {
                    $scores[$lang] += 2;
                }
            }
        }

        foreach (self::DIACRITICS as $char => $lang) {
            if (mb_strpos($lower, $char) !== false) {
                $scores[$lang] += 1;
            }
        }

        arsort($scores);
        $best = array_key_first($scores);

        return $scores[$best] > 0 ? (string) $best : self::DEFAULT;
    }
}
