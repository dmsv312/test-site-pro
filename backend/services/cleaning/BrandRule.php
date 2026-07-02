<?php

declare(strict_types=1);

namespace app\services\cleaning;

/**
 * Flags brand keywords: our own brand (site.pro) and competitor brands. The term list is
 * editable in the admin area (see {@see \app\models\BrandTerm}); this rule just applies it.
 *
 * A brand term matches only at word boundaries — the start/end of the term or a non-letter/
 * non-digit character (space, dot, etc.). So "wix" matches "wix templates" and "wix.com" but
 * not "wixel", and "tilda" does not match the Spanish "tildar" or "matilda". This targets brand
 * *names*, not the whole competitor source — non-brand competitor keywords survive on purpose
 * (they feed the gap-analysis idea in docs/PLAN.md).
 */
final class BrandRule
{
    /** @param string[] $brandTerms lowercased brand terms */
    public function __construct(private readonly array $brandTerms)
    {
    }

    /** @return string|null a drop reason, or null if no brand term matches */
    public function reason(string $normalizedTerm): ?string
    {
        foreach ($this->brandTerms as $brand) {
            if ($brand === '') {
                continue;
            }
            // Boundary = string start/end or any char that isn't a letter or digit. Matching the
            // whole normalized term (tokens are space-joined) treats spaces and punctuation as
            // boundaries, so a brand never matches inside a longer word.
            $pattern = '/(?<![\p{L}\p{N}])' . preg_quote($brand, '/') . '(?![\p{L}\p{N}])/u';
            if (preg_match($pattern, $normalizedTerm) === 1) {
                return "Brand term: \"{$brand}\"";
            }
        }

        return null;
    }
}
