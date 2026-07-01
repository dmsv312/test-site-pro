<?php

declare(strict_types=1);

namespace app\services\import;

/**
 * Reads a source into a list of associative rows (column => value). One implementation
 * per transport (CSV, JSON now; an API reader is the documented "later" seam). Adapters
 * consume these rows and never care where they came from.
 */
interface SourceReaderInterface
{
    /**
     * @return array<int, array<string, mixed>>
     * @throws \RuntimeException on unreadable or malformed input
     */
    public function read(string $path): array;
}
