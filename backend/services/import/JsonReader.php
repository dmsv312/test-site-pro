<?php

declare(strict_types=1);

namespace app\services\import;

/**
 * Reads a JSON file containing a list of row objects — either a bare array
 * `[ {...}, {...} ]` or a wrapper `{ "data": [ {...} ] }`. Non-object entries are ignored.
 */
final class JsonReader implements SourceReaderInterface
{
    public function read(string $path): array
    {
        $content = @file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Cannot read JSON file: {$path}");
        }

        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $data = json_decode((string) $content, true);

        if (!is_array($data)) {
            throw new \RuntimeException('Invalid JSON: expected an array of row objects.');
        }

        // Support a { "data": [...] } wrapper.
        if (isset($data['data']) && is_array($data['data'])) {
            $data = $data['data'];
        }

        $rows = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $rows[] = $item;
            }
        }

        return $rows;
    }
}
