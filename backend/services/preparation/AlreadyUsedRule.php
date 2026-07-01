<?php

declare(strict_types=1);

namespace app\services\preparation;

use app\models\Keyword;

/**
 * Flags keywords Site.pro already runs in Google Ads, so preparation yields only net-new terms.
 *
 * "Already used" is defined as the exact normalized term appearing in the `google_ads` source —
 * that source *is* the account's current keyword list (see docs/PLAN.md, stage-5 decision). The
 * match is on the whole normalized term (exact), not a substring: a keyword is already-used only
 * when it is the same search term, not merely because it contains one. A google_ads keyword that
 * survived cleaning therefore flags itself here, which is the point — we exclude what's live.
 */
final class AlreadyUsedRule
{
    /** @var array<string, true> normalized terms already present in the google_ads source */
    private array $used = [];

    /** @param string[] $usedTerms normalized terms from the google_ads source */
    public function __construct(array $usedTerms)
    {
        foreach ($usedTerms as $term) {
            if ($term !== '') {
                $this->used[$term] = true;
            }
        }
    }

    /** Build the used-set from the current google_ads keyword rows (any stage). */
    public static function fromDatabase(): self
    {
        /** @var string[] $terms */
        $terms = Keyword::find()
            ->select('normalized_term')
            ->where(['source' => Keyword::SOURCE_GOOGLE_ADS])
            ->distinct()
            ->column();

        return new self($terms);
    }

    /** @return string|null a drop reason, or null if the term is not already used */
    public function reason(string $normalizedTerm): ?string
    {
        return isset($this->used[$normalizedTerm]) ? 'already used in Google Ads' : null;
    }
}
