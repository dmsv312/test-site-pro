<?php

declare(strict_types=1);

namespace app\services\adgen;

/**
 * The copy of one responsive search ad, independent of any target URL (the URL is authoritative
 * from the ad group, never from the copy — untrusted generated text can't redirect a campaign).
 * An immutable value produced by a generator ({@see TemplateAdGenerator}) or loaded from stored
 * offline-authored content ({@see StoredAdSource}), then checked by {@see RsaValidator} before it
 * is persisted.
 */
final class AdContent
{
    /**
     * @param string[] $headlines    up to 15, each ≤30 chars
     * @param string[] $descriptions up to 4, each ≤90 chars
     */
    public function __construct(
        public readonly string $language,
        public readonly array $headlines,
        public readonly array $descriptions,
        public readonly ?string $path1 = null,
        public readonly ?string $path2 = null,
    ) {
    }

    /**
     * Build from a decoded stored entry (untrusted). Missing/oddly-typed fields become empty so the
     * validator — not a type error — rejects them.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(string $language, array $data): self
    {
        $strings = static function ($value): array {
            if (!is_array($value)) {
                return [];
            }
            $out = [];
            foreach ($value as $item) {
                if (is_string($item)) {
                    $out[] = trim($item);
                }
            }

            return $out;
        };
        $path = static function ($value): ?string {
            $value = is_string($value) ? trim($value) : '';

            return $value === '' ? null : $value;
        };

        return new self(
            $language,
            $strings($data['headlines'] ?? []),
            $strings($data['descriptions'] ?? []),
            $path($data['path1'] ?? null),
            $path($data['path2'] ?? null),
        );
    }
}
