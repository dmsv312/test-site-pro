<?php

declare(strict_types=1);

namespace app\services\preparation;

/**
 * Flags keywords that contain a forbidden term. The list is editable in the admin area
 * (see {@see \app\models\ForbiddenTerm}); this rule just applies it.
 *
 * Matches at word boundaries, exactly like {@see \app\services\cleaning\BrandRule}: the start/end
 * of the term or any non-letter/non-digit character. So a short forbidden word never matches
 * inside a longer, unrelated word. The list ships empty; whatever Site.pro bans goes in the admin.
 */
final class ForbiddenRule
{
    /** @param string[] $forbiddenTerms lowercased forbidden terms */
    public function __construct(private readonly array $forbiddenTerms)
    {
    }

    /** @return string|null a drop reason, or null if no forbidden term matches */
    public function reason(string $normalizedTerm): ?string
    {
        foreach ($this->forbiddenTerms as $term) {
            if ($term === '') {
                continue;
            }
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($term, '/') . '(?![\p{L}\p{N}])/u';
            if (preg_match($pattern, $normalizedTerm) === 1) {
                return "Blocked term: \"{$term}\"";
            }
        }

        return null;
    }
}
