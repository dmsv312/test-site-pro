<?php

declare(strict_types=1);

namespace app\services\adgen;

/**
 * Offline-authored ad copy, keyed by `"{language}:{theme_key}"`. This is decision 3 in practice:
 * higher-quality copy is written ahead of time (locally, with a Claude Code CLI), committed as a
 * plain JSON file, and preferred by {@see AdGenerationService} when an entry exists and passes
 * {@see RsaValidator}. The deployed host thus needs no AI credentials and makes no per-request call;
 * anything without stored copy (or whose stored copy fails validation) falls back to the template
 * engine.
 *
 * The file is untrusted like any other input: a missing file, malformed JSON, or an odd entry
 * simply yields "no stored copy" for that key, never an error.
 */
final class StoredAdSource
{
    /** @param array<string, mixed> $entries key => raw entry */
    public function __construct(private readonly array $entries)
    {
    }

    /** Load the stored copy from a JSON file; a missing/invalid file means "no stored copy". */
    public static function fromFile(string $path): self
    {
        if (!is_file($path) || !is_readable($path)) {
            return new self([]);
        }
        $decoded = json_decode((string) file_get_contents($path), true);

        return new self(is_array($decoded) ? $decoded : []);
    }

    /** The stored copy for a group, or null if there is none. */
    public function get(string $language, string $themeKey): ?AdContent
    {
        $entry = $this->entries[$language . ':' . $themeKey] ?? null;
        if (!is_array($entry)) {
            return null;
        }

        return AdContent::fromArray($language, $entry);
    }
}
