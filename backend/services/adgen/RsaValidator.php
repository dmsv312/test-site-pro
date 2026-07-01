<?php

declare(strict_types=1);

namespace app\services\adgen;

/**
 * Validates ad copy against Google's responsive-search-ad (RSA) limits. Generated copy — whether
 * from the template engine or from stored offline-authored content — is treated as untrusted input:
 * it must clear every rule here before it can be stored, so a malformed or over-long headline can
 * never reach the export file.
 *
 * RSA limits (Google Ads):
 *   - 3–15 headlines, each ≤ 30 characters, all distinct;
 *   - 2–4 descriptions, each ≤ 90 characters, all distinct;
 *   - optional display paths (`path1`, `path2`), each ≤ 15 characters, no whitespace or '/'.
 * Every string must also be valid UTF-8 with no control characters (keeps junk out of the export).
 */
final class RsaValidator
{
    public const HEADLINE_MAX = 30;
    public const DESCRIPTION_MAX = 90;
    public const PATH_MAX = 15;

    public const MIN_HEADLINES = 3;
    public const MAX_HEADLINES = 15;
    public const MIN_DESCRIPTIONS = 2;
    public const MAX_DESCRIPTIONS = 4;

    /**
     * @return string[] the reasons the copy is invalid; an empty array means it passes.
     */
    public function validate(AdContent $ad): array
    {
        $errors = [];

        $this->checkList(
            $ad->headlines,
            'Headline',
            self::MIN_HEADLINES,
            self::MAX_HEADLINES,
            self::HEADLINE_MAX,
            $errors,
        );
        $this->checkList(
            $ad->descriptions,
            'Description',
            self::MIN_DESCRIPTIONS,
            self::MAX_DESCRIPTIONS,
            self::DESCRIPTION_MAX,
            $errors,
        );

        foreach (['path1' => $ad->path1, 'path2' => $ad->path2] as $name => $path) {
            if ($path === null || $path === '') {
                continue;
            }
            if (!$this->isClean($path)) {
                $errors[] = "{$name} contains invalid characters.";
            } elseif (mb_strlen($path, 'UTF-8') > self::PATH_MAX) {
                $errors[] = "{$name} exceeds " . self::PATH_MAX . ' characters.';
            } elseif (preg_match('~[\s/]~u', $path) === 1) {
                $errors[] = "{$name} must not contain spaces or '/'.";
            }
        }

        return $errors;
    }

    public function isValid(AdContent $ad): bool
    {
        return $this->validate($ad) === [];
    }

    /**
     * @param string[] $items
     * @param string[] $errors passed by reference — appended to
     */
    private function checkList(
        array $items,
        string $label,
        int $min,
        int $max,
        int $maxLen,
        array &$errors,
    ): void {
        $count = count($items);
        if ($count < $min) {
            $errors[] = "Needs at least {$min} {$label}(s); got {$count}.";
        }
        if ($count > $max) {
            $errors[] = "Allows at most {$max} {$label}(s); got {$count}.";
        }

        $seen = [];
        foreach ($items as $item) {
            if (trim($item) === '') {
                $errors[] = "{$label} is empty.";
                continue;
            }
            if (!$this->isClean($item)) {
                $errors[] = "{$label} \"{$item}\" contains invalid characters.";
                continue;
            }
            if (mb_strlen($item, 'UTF-8') > $maxLen) {
                $errors[] = "{$label} \"{$item}\" exceeds {$maxLen} characters.";
            }
            $key = mb_strtolower($item, 'UTF-8');
            if (isset($seen[$key])) {
                $errors[] = "Duplicate {$label} \"{$item}\".";
            }
            $seen[$key] = true;
        }
    }

    /** Valid UTF-8 and free of C0/C1 control characters (so no junk lands in the export). */
    private function isClean(string $value): bool
    {
        if (!mb_check_encoding($value, 'UTF-8')) {
            return false;
        }

        return preg_match('/[\x00-\x1F\x7F]/u', $value) !== 1;
    }
}
