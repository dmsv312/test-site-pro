<?php

declare(strict_types=1);

namespace app\services\import;

use app\models\Keyword;
use app\services\KeywordNormalizer;
use app\services\LanguageDetector;

/**
 * Shared parsing helpers for the concrete adapters: number/URL cleanup, competition
 * bucketing, language normalization, and the common tail every mapped row carries
 * (source, raw + normalized term, language, raw_data, stage).
 */
abstract class AbstractKeywordAdapter implements KeywordAdapterInterface
{
    public function __construct(
        protected readonly KeywordNormalizer $normalizer,
        protected readonly LanguageDetector $detector,
    ) {
    }

    protected function str(mixed $v): string
    {
        return trim((string) ($v ?? ''));
    }

    /**
     * Parse a cell into a float, or null if it isn't an unambiguous number. We strip currency
     * symbols, spaces, and *thousands* commas (only in a clear grouping pattern), then require
     * a genuine numeric string — scientific notation included. Ambiguous inputs (a lone decimal
     * comma like "12,50", letter-suffixed "1.2K") are rejected as null rather than silently
     * mangled into a wrong magnitude.
     */
    protected function toNumber(mixed $v): ?float
    {
        if ($v === null || $v === '') {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return is_finite((float) $v) ? (float) $v : null;
        }

        $s = trim((string) $v);
        // Drop currency symbols, percent, spaces and non-breaking spaces.
        $s = preg_replace('/[\s\x{00A0}$€£¥%]/u', '', $s) ?? $s;
        // Remove thousands separators only in a clear grouping pattern (1,234 / 1,234,567.89).
        if (preg_match('/^-?\d{1,3}(,\d{3})+(\.\d+)?$/', $s)) {
            $s = str_replace(',', '', $s);
        }
        if ($s === '' || !is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    protected function toInt(mixed $v): ?int
    {
        $n = $this->toNumber($v);

        return $n === null ? null : (int) round($n);
    }

    /** Returns a numeric string (2 decimals) or null. */
    protected function toDecimal(mixed $v): ?string
    {
        $n = $this->toNumber($v);

        return $n === null ? null : number_format($n, 2, '.', '');
    }

    protected function toUrl(mixed $v): ?string
    {
        $s = $this->str($v);

        return $s === '' ? null : mb_substr($s, 0, 1000);
    }

    protected function normLang(mixed $v): ?string
    {
        $l = strtolower($this->str($v));

        return $l === '' ? null : mb_substr($l, 0, 8);
    }

    /** LOW | MEDIUM | HIGH from a text label or a 0–1 / 0–100 index; null if unknown. */
    protected function competition(mixed $v): ?string
    {
        $s = strtoupper($this->str($v));
        if ($s === '') {
            return null;
        }
        if (in_array($s, ['LOW', 'MEDIUM', 'HIGH'], true)) {
            return $s;
        }
        if (is_numeric($s)) {
            $n = (float) $s;
            if ($n <= 1.0) {
                $n *= 100;
            }

            return $n < 34 ? 'LOW' : ($n < 67 ? 'MEDIUM' : 'HIGH');
        }

        return null;
    }

    /**
     * The fields every source shares. $language may be null → the caller decides whether to
     * fall back to detection.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    protected function base(string $rawTerm, ?string $language, array $row): array
    {
        // JSON_INVALID_UTF8_SUBSTITUTE keeps encoding from returning false on stray bytes; the
        // ?: null guard means a genuinely un-encodable row stores NULL, not an empty string, so
        // the audit copy is never silently blanked.
        $rawData = json_encode($row, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

        return [
            'source' => $this->source(),
            'raw_term' => mb_substr($rawTerm, 0, 500),
            'normalized_term' => mb_substr($this->normalizer->normalize($rawTerm), 0, 500),
            'language' => $language,
            'raw_data' => $rawData === false ? null : $rawData,
            'stage' => Keyword::STAGE_IMPORTED,
        ];
    }

    /** Language from the row's column if present, otherwise heuristic detection. */
    protected function resolveLanguage(mixed $rawLang, string $term): string
    {
        return $this->normLang($rawLang) ?? $this->detector->detect($term);
    }
}
