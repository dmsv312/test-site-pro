<?php

declare(strict_types=1);

namespace app\commands;

use Yii;
use app\models\ImportBatch;
use app\models\Keyword;
use app\services\import\ImportService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Command-line import — the same {@see ImportService} the web upload uses, so the pipeline is
 * proven decoupled from the web layer.
 *
 *   yii import/samples            import all four sample-data files
 *   yii import/file <src> <path>  import one file (src = a Keyword::SOURCE_* key)
 */
class ImportController extends Controller
{
    /** Maps each sample file to its source. */
    private const SAMPLES = [
        'google_ads_keywords.csv' => Keyword::SOURCE_GOOGLE_ADS,
        'search_console_queries.csv' => Keyword::SOURCE_SEARCH_CONSOLE,
        'ahrefs_organic_keywords.csv' => Keyword::SOURCE_AHREFS_ORGANIC,
        'ahrefs_paid_keywords.csv' => Keyword::SOURCE_AHREFS_PAID,
    ];

    /** Import one file. */
    public function actionFile(string $source, string $path): int
    {
        if (!in_array($source, Keyword::SOURCES, true)) {
            $this->stderr('Unknown source: ' . $source . '. Valid: ' . implode(', ', Keyword::SOURCES) . "\n", Console::FG_RED);

            return ExitCode::USAGE;
        }
        if (!is_file($path)) {
            $this->stderr("File not found: {$path}\n", Console::FG_RED);

            return ExitCode::NOINPUT;
        }

        $format = strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'json' ? 'json' : 'csv';
        $batch = ImportService::create()->import($source, $format, $path, basename($path));

        return $this->report($batch);
    }

    /** Import all four sample files from the sample-data directory. */
    public function actionSamples(?string $dir = null): int
    {
        $dir ??= is_dir('/opt/sample-data')
            ? '/opt/sample-data'
            : dirname((string) Yii::getAlias('@app')) . '/sample-data';

        if (!is_dir($dir)) {
            $this->stderr("Sample directory not found: {$dir}\n", Console::FG_RED);

            return ExitCode::NOINPUT;
        }

        $service = ImportService::create();
        $ok = true;
        foreach (self::SAMPLES as $file => $source) {
            $path = $dir . '/' . $file;
            if (!is_file($path)) {
                $this->stderr("  missing: {$path}\n", Console::FG_YELLOW);
                $ok = false;
                continue;
            }
            $batch = $service->import($source, 'csv', $path, $file);
            $this->report($batch);
            $ok = $ok && $batch->status === ImportBatch::STATUS_IMPORTED;
        }

        $this->stdout('Total keywords now: ' . Keyword::find()->count() . "\n", Console::FG_CYAN);

        return $ok ? ExitCode::OK : ExitCode::UNSPECIFIED_ERROR;
    }

    private function report(ImportBatch $batch): int
    {
        if ($batch->status === ImportBatch::STATUS_IMPORTED) {
            $this->stdout(
                sprintf("✓ %-24s %s\n", $batch->source, $batch->message),
                Console::FG_GREEN,
            );

            return ExitCode::OK;
        }

        $this->stderr(sprintf("✗ %-24s %s\n", $batch->source, $batch->message), Console::FG_RED);

        return ExitCode::UNSPECIFIED_ERROR;
    }
}
