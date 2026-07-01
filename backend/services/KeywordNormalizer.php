<?php

declare(strict_types=1);

namespace app\services;

/**
 * Turns a raw term into its normalized form: lowercased, trimmed, whitespace-collapsed,
 * and token-sorted. Token-sorting means "web builder" and "builder web" normalize to the
 * same string, which is what the dedup/merge step (stage 5) groups on.
 */
final class KeywordNormalizer
{
    public function normalize(string $term): string
    {
        $lower = mb_strtolower(trim($term), 'UTF-8');
        // Collapse any run of whitespace to a single space.
        $collapsed = preg_replace('/\s+/u', ' ', $lower) ?? $lower;
        $collapsed = trim($collapsed);

        if ($collapsed === '') {
            return '';
        }

        $tokens = explode(' ', $collapsed);
        sort($tokens, SORT_STRING);

        return implode(' ', $tokens);
    }
}
