<?php

declare(strict_types=1);

namespace app\services\import;

/**
 * Maps one source's raw rows onto the unified `keyword` record. One adapter per source.
 * Unknown columns are ignored; missing required columns fail the whole batch with a clear
 * message (checked once, up front, by the import service).
 */
interface KeywordAdapterInterface
{
    /** The source key this adapter handles (Keyword::SOURCE_*). */
    public function source(): string;

    /** @return string[] columns that MUST be present in the file header */
    public function requiredColumns(): array;

    /**
     * Map one raw row to unified keyword attributes, or return null to skip the row.
     *
     * @param array<string, mixed> $row
     * @return array<string, mixed>|null
     */
    public function map(array $row): ?array;
}
