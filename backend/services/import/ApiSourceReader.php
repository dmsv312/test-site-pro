<?php

declare(strict_types=1);

namespace app\services\import;

use yii\base\NotSupportedException;

/**
 * Documented seam for the assignment's "later we will use API". Search Console, Google Ads,
 * and Ahrefs all expose APIs; when access is granted, a live reader is wired in here and the
 * adapters + the rest of the pipeline stay untouched — the source just stops being a file.
 * Not implemented yet.
 */
final class ApiSourceReader implements SourceReaderInterface
{
    public function read(string $path): array
    {
        throw new NotSupportedException(
            'API import is a planned seam and is not implemented yet. Import CSV or JSON files.',
        );
    }
}
