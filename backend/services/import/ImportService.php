<?php

declare(strict_types=1);

namespace app\services\import;

use app\models\ImportBatch;
use app\models\Keyword;
use app\services\KeywordNormalizer;
use app\services\LanguageDetector;
use app\services\import\adapters\AhrefsOrganicAdapter;
use app\services\import\adapters\AhrefsPaidAdapter;
use app\services\import\adapters\GoogleAdsAdapter;
use app\services\import\adapters\SearchConsoleAdapter;

/**
 * Orchestrates one import: read the file → check required columns → map each row through the
 * source's adapter → bulk-insert into `keyword` → record the batch. Everything runs in one
 * transaction; on any failure the transaction is rolled back and a single `failed` batch row
 * is written so the admin history still shows the attempt and why it failed.
 */
final class ImportService
{
    /** Columns written by the bulk insert; the rest use their DB defaults (flags=false). */
    private const INSERT_COLUMNS = [
        'batch_id', 'source', 'raw_term', 'normalized_term', 'language', 'geo',
        'avg_monthly_searches', 'cpc', 'competition', 'competitor_domain', 'source_url',
        'clicks', 'impressions', 'position', 'raw_data', 'stage', 'created_at',
    ];

    /**
     * @param array<string, KeywordAdapterInterface> $adapters keyed by source
     * @param array<string, SourceReaderInterface> $readers keyed by format (csv|json)
     */
    public function __construct(
        private readonly array $adapters,
        private readonly array $readers,
    ) {
    }

    /** Wires the default adapters and file readers. */
    public static function create(): self
    {
        $normalizer = new KeywordNormalizer();
        $detector = new LanguageDetector();

        $adapters = [];
        foreach ([GoogleAdsAdapter::class, SearchConsoleAdapter::class, AhrefsOrganicAdapter::class, AhrefsPaidAdapter::class] as $class) {
            $adapter = new $class($normalizer, $detector);
            $adapters[$adapter->source()] = $adapter;
        }

        return new self(
            $adapters,
            [
                'csv' => new CsvReader(),
                'json' => new JsonReader(),
                // 'api' => new ApiSourceReader(), // documented seam, not wired yet
            ],
        );
    }

    /** @return string[] supported source keys */
    public function sources(): array
    {
        return array_keys($this->adapters);
    }

    /**
     * @param string $source Keyword::SOURCE_*
     * @param string $format csv|json
     * @param string $path absolute path to the uploaded file
     * @param string $filename original filename (for the history)
     */
    public function import(string $source, string $format, string $path, string $filename): ImportBatch
    {
        $format = strtolower($format);
        if (!isset($this->adapters[$source])) {
            return $this->failedBatch($source, $format, $filename, 0, "Unknown source: {$source}");
        }
        if (!isset($this->readers[$format])) {
            return $this->failedBatch($source, $format, $filename, 0, "Unsupported format: {$format}");
        }

        $adapter = $this->adapters[$source];
        $rowsTotal = 0;

        $batch = new ImportBatch();
        $batch->source = $source;
        $batch->filename = $filename;
        $batch->format = $format;
        $batch->status = ImportBatch::STATUS_IMPORTED;

        $db = ImportBatch::getDb();
        $transaction = $db->beginTransaction();

        try {
            $rows = $this->readers[$format]->read($path);
            $rowsTotal = count($rows);
            $batch->rows_total = $rowsTotal;

            if ($rows === []) {
                throw new \RuntimeException('No rows found in the file.');
            }

            // Union of keys across all rows — a CSV has a fixed header, but JSON objects can be
            // heterogeneous, so checking only the first row would wrongly fail (or pass) a batch.
            $header = [];
            foreach ($rows as $row) {
                foreach (array_keys($row) as $key) {
                    $header[$key] = true;
                }
            }
            $header = array_keys($header);

            $missing = array_values(array_diff($adapter->requiredColumns(), $header));
            if ($missing !== []) {
                throw new \RuntimeException(
                    'Missing required column(s): ' . implode(', ', $missing)
                    . '. Found: ' . implode(', ', $header) . '.',
                );
            }

            $batch->save(false); // assigns the id used as batch_id below
            $now = time();

            $insertRows = [];
            $skipped = 0;
            foreach ($rows as $row) {
                $mapped = $adapter->map($row);
                if ($mapped === null) {
                    $skipped++;
                    continue;
                }
                $insertRows[] = $this->toInsertRow((int) $batch->id, $mapped, $now);
            }

            if ($insertRows !== []) {
                $db->createCommand()
                    ->batchInsert('{{%keyword}}', self::INSERT_COLUMNS, $insertRows)
                    ->execute();
            }

            $batch->rows_imported = count($insertRows);
            $batch->rows_skipped = $skipped;
            $batch->message = sprintf(
                'Imported %d, skipped %d of %d rows.',
                $batch->rows_imported,
                $skipped,
                $rowsTotal,
            );
            $batch->save(false);

            $transaction->commit();

            return $batch;
        } catch (\Throwable $e) {
            $transaction->rollBack();

            return $this->failedBatch($source, $format, $filename, $rowsTotal, $e->getMessage());
        }
    }

    /**
     * @param array<string, mixed> $m mapped attributes from an adapter
     * @return array<int, mixed> values in INSERT_COLUMNS order
     */
    private function toInsertRow(int $batchId, array $m, int $now): array
    {
        return [
            $batchId,
            $m['source'],
            $m['raw_term'],
            $m['normalized_term'],
            $m['language'] ?? null,
            $m['geo'] ?? null,
            $m['avg_monthly_searches'] ?? null,
            $m['cpc'] ?? null,
            $m['competition'] ?? null,
            $m['competitor_domain'] ?? null,
            $m['source_url'] ?? null,
            $m['clicks'] ?? null,
            $m['impressions'] ?? null,
            $m['position'] ?? null,
            $m['raw_data'] ?? null,
            $m['stage'] ?? Keyword::STAGE_IMPORTED,
            $now,
        ];
    }

    /** Persists a failed attempt outside the rolled-back transaction so history keeps it. */
    private function failedBatch(
        string $source,
        string $format,
        string $filename,
        int $rowsTotal,
        string $message,
    ): ImportBatch {
        $batch = new ImportBatch();
        $batch->source = $source;
        $batch->filename = $filename;
        $batch->format = $format;
        $batch->rows_total = $rowsTotal;
        $batch->rows_imported = 0;
        $batch->rows_skipped = 0;
        $batch->status = ImportBatch::STATUS_FAILED;
        $batch->message = $message;
        $batch->save(false);

        return $batch;
    }
}
