<?php

declare(strict_types=1);

namespace app\services\cleaning;

/**
 * Drops low-volume keywords — those not worth advertising on. Runs last, on the already-clean
 * set, so the funnel's "below volume" count reflects real candidates rather than junk.
 *
 * A null volume means the source didn't provide one (e.g. Search Console reports clicks and
 * impressions, not search volume). We can't judge those, so we keep them: the rule drops a
 * keyword only when it has a volume and that volume is below the (editable) threshold.
 */
final class VolumeRule
{
    public function __construct(private readonly int $minVolume)
    {
    }

    /** @return string|null a drop reason, or null to keep the keyword */
    public function reason(?int $avgMonthlySearches): ?string
    {
        if ($avgMonthlySearches === null) {
            return null;
        }

        if ($avgMonthlySearches < $this->minVolume) {
            return "Search volume too low ({$avgMonthlySearches}/mo, min {$this->minVolume})";
        }

        return null;
    }
}
