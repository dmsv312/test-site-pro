<?php

declare(strict_types=1);

namespace app\services\export;

/**
 * Shared, pure CSV renderer for the export formats. One RFC-4180 implementation used by both
 * {@see GoogleAdsEditorExport} (the desktop Editor file) and {@see GoogleAdsBulkUploadExport}
 * (the web-UI bulk-upload sheets), so the quoting/encoding rules can't drift between them.
 *
 * Output is RFC-4180: comma-separated, `"`-quoted with doubled inner quotes and no backslash
 * escapes, CRLF line endings, UTF-8 without a BOM — the encoding Google's importers accept.
 */
final class CsvWriter
{
    /**
     * Render an ordered header plus rows (each an associative partial map keyed by column name;
     * columns a row omits are emitted empty) to a CSV string.
     *
     * @param string[]                             $header
     * @param array<int, array<string, string>>    $rows
     */
    public static function render(array $header, array $rows): string
    {
        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            return '';
        }

        // escape='' → RFC-4180 quoting (double the inner quote, no backslash escapes); CRLF line ends.
        fputcsv($stream, $header, ',', '"', '', "\r\n");
        foreach ($rows as $row) {
            $line = [];
            foreach ($header as $column) {
                $line[] = $row[$column] ?? '';
            }
            fputcsv($stream, $line, ',', '"', '', "\r\n");
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv === false ? '' : $csv;
    }
}
