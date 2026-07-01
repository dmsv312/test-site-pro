<?php

declare(strict_types=1);

namespace app\services\import;

/**
 * Reads a CSV file: the first non-empty line is the header; every later line becomes an
 * associative row keyed by that header. Handles a UTF-8 BOM and skips blank lines. Uses
 * RFC-4180 quoting (no backslash escaping).
 */
final class CsvReader implements SourceReaderInterface
{
    public function read(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Cannot open CSV file: {$path}");
        }

        $rows = [];
        $header = null;

        try {
            while (($data = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                // fgetcsv yields [null] for a blank line.
                if ($data === [null]) {
                    continue;
                }

                if ($header === null) {
                    if (isset($data[0])) {   // fgetcsv yields string|null; isset excludes the null
                        $data[0] = preg_replace('/^\xEF\xBB\xBF/', '', $data[0]);
                    }
                    $header = array_map(fn($h): string => trim($this->utf8((string) $h)), $data);

                    $dupes = array_keys(array_filter(array_count_values($header), static fn($n): bool => $n > 1));
                    if ($dupes !== []) {
                        throw new \RuntimeException('Duplicate header column(s): ' . implode(', ', $dupes) . '.');
                    }
                    continue;
                }

                // Skip a row that is entirely empty.
                $nonEmpty = array_filter($data, static fn($v): bool => $v !== null && $v !== '');
                if ($nonEmpty === []) {
                    continue;
                }

                $row = [];
                foreach ($header as $i => $col) {
                    $cell = $data[$i] ?? null;
                    $row[$col] = is_string($cell) ? $this->utf8($cell) : $cell;
                }
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * Ensure a cell is valid UTF-8. Excel/Ahrefs often export Windows-1252; Postgres (UTF-8)
     * would reject the raw bytes on insert, so we convert instead of corrupting or crashing.
     */
    private function utf8(string $value): string
    {
        if ($value === '' || mb_check_encoding($value, 'UTF-8')) {
            return $value;
        }

        return mb_convert_encoding($value, 'UTF-8', 'Windows-1252');
    }
}
