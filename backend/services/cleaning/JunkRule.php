<?php

declare(strict_types=1);

namespace app\services\cleaning;

/**
 * Flags keywords that can't be useful ad terms. Runs first in the cleaning pipeline, before
 * dedup, so obvious garbage never reaches the later rules. Operates on the already-normalized
 * term (lowercased, whitespace-collapsed, token-sorted) and returns a human-readable reason,
 * or null to keep the keyword.
 *
 * Checks, in order: single character, digits-only, symbols-only, over-length, stopword-only,
 * and a deliberately narrow gibberish check (see {@see hasGibberishToken()}).
 */
final class JunkRule
{
    /**
     * A small set of English function words. A term made up *only* of these carries no intent
     * and is dropped. Kept intentionally small and English-only: the goal is to catch obvious
     * cases like "the" or "and for", not to be a full multi-language stopword filter.
     *
     * @var array<string, true>
     */
    private const STOPWORDS = [
        'a' => true, 'an' => true, 'and' => true, 'are' => true, 'as' => true, 'at' => true,
        'be' => true, 'but' => true, 'by' => true, 'for' => true, 'from' => true, 'in' => true,
        'is' => true, 'it' => true, 'of' => true, 'on' => true, 'or' => true, 'the' => true,
        'this' => true, 'to' => true, 'with' => true,
    ];

    /** Vowels across the languages we handle (en/de/es/fr/it/pt), including accented forms and y. */
    private const VOWELS = 'aeiouyàâäáãåæèéêëìíîïòóôöõøùúûüýÿœ';

    public function __construct(private readonly int $maxTermLength)
    {
    }

    /** @return string|null a drop reason, or null if the term is not junk */
    public function reason(string $normalizedTerm): ?string
    {
        $term = trim($normalizedTerm);

        if ($term === '') {
            return 'Empty term';
        }

        $length = mb_strlen($term, 'UTF-8');
        if ($length === 1) {
            return 'Single character';
        }

        // No letter at all → it's digits or symbols, never a keyword.
        if (preg_match('/\p{L}/u', $term) !== 1) {
            return preg_match('/\p{N}/u', $term) === 1 ? 'Numbers only' : 'Symbols only';
        }

        if ($length > $this->maxTermLength) {
            return "Too long (over {$this->maxTermLength} characters)";
        }

        if ($this->isStopwordOnly($term)) {
            return 'Common words only';
        }

        if ($this->hasGibberishToken($term)) {
            return 'Looks like gibberish';
        }

        return null;
    }

    /** True when every token is an English stopword. */
    private function isStopwordOnly(string $term): bool
    {
        foreach (explode(' ', $term) as $token) {
            if (!isset(self::STOPWORDS[$token])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Narrow, multilingual-safe gibberish check. A token counts as gibberish only when it is
     * all letters, at least 5 long, and contains no vowel at all — e.g. "zxcvbnm". Real words
     * in the languages we handle always contain a vowel within a few letters (German consonant
     * clusters like "durchschnitt" still have vowels), so this will not flag legitimate terms,
     * while it still catches keyboard-mash queries. Any one gibberish token condemns the term.
     */
    private function hasGibberishToken(string $term): bool
    {
        foreach (explode(' ', $term) as $token) {
            if (mb_strlen($token, 'UTF-8') < 5) {
                continue;
            }
            if (preg_match('/[^\p{L}]/u', $token) === 1) {
                continue; // not all letters — handled by other checks, not "gibberish"
            }
            if (preg_match('/[' . self::VOWELS . ']/iu', $token) !== 1) {
                return true;
            }
        }

        return false;
    }
}
