<?php

declare(strict_types=1);

namespace app\commands;

use Yii;
use app\services\export\ExportService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Console entry point for stage-7 export — the same {@see ExportService} the admin area uses, proving
 * the logic is decoupled from the web layer.
 *
 *   yii export/file [path]     write the Google Ads Editor CSV (default: @runtime/export/…)
 *   yii export/bulk [path]     write the Google Ads web-UI bulk-upload ZIP (default: @runtime/export/…)
 */
class ExportController extends Controller
{
    /** Write the Google Ads Editor CSV to $path (or a default under runtime) and print a summary. */
    public function actionFile(?string $path = null): int
    {
        $summary = ExportService::snapshot();
        if ((int) $summary['adRows'] === 0) {
            $this->stderr("Nothing to export: no valid ads. Run `prepare/run` then `adgen/run` first.\n", Console::FG_RED);

            return ExitCode::UNAVAILABLE;
        }

        $path ??= Yii::getAlias('@runtime/export/google-ads-editor-' . date('Ymd') . '.csv');
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->stderr("Cannot create directory: {$dir}\n", Console::FG_RED);

            return ExitCode::CANTCREAT;
        }

        $csv = (new ExportService())->toCsv();
        if (file_put_contents($path, $csv) === false) {
            $this->stderr("Cannot write file: {$path}\n", Console::FG_RED);

            return ExitCode::IOERR;
        }

        $this->stdout("Export done.\n", Console::FG_GREEN);
        $this->stdout(sprintf("  File: %s (%s bytes)\n", $path, number_format(strlen($csv))));
        $this->stdout(sprintf(
            "  %d row(s): %d keyword(s) + %d responsive search ad(s) across %d campaign(s) / %d ad group(s).\n",
            (int) $summary['totalRows'],
            (int) $summary['keywordRows'],
            (int) $summary['adRows'],
            (int) $summary['campaigns'],
            (int) $summary['adGroups'],
        ));
        $this->stdout(sprintf("  Match type: %s.\n", $summary['matchType']));
        if ((int) $summary['groupsWithoutAd'] > 0) {
            $this->stdout(sprintf(
                "  Note: %d ad group(s) have no valid ad — their keywords are exported without an ad.\n",
                (int) $summary['groupsWithoutAd'],
            ), Console::FG_YELLOW);
        }

        return ExitCode::OK;
    }

    /** Write the Google Ads web-UI bulk-upload ZIP (one CSV per entity + README) and print a summary. */
    public function actionBulk(?string $path = null): int
    {
        $summary = ExportService::snapshot();
        if ((int) $summary['adRows'] === 0) {
            $this->stderr("Nothing to export: no valid ads. Run `prepare/run` then `adgen/run` first.\n", Console::FG_RED);

            return ExitCode::UNAVAILABLE;
        }

        $path ??= Yii::getAlias('@runtime/export/google-ads-bulk-upload-' . date('Ymd') . '.zip');
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            $this->stderr("Cannot create directory: {$dir}\n", Console::FG_RED);

            return ExitCode::CANTCREAT;
        }

        $service = new ExportService();
        $zip = $service->toBulkZip();
        if ($zip === '' || file_put_contents($path, $zip) === false) {
            $this->stderr("Cannot write archive: {$path}\n", Console::FG_RED);

            return ExitCode::IOERR;
        }

        $this->stdout("Bulk export done.\n", Console::FG_GREEN);
        $this->stdout(sprintf("  File: %s (%s bytes)\n", $path, number_format(strlen($zip))));
        $this->stdout("  Sheets (upload in order via Tools > Bulk actions > Uploads):\n");
        foreach ($service->bulkSheets() as $sheet) {
            $this->stdout(sprintf("    %-26s %d row(s)\n", $sheet['filename'], count($sheet['rows'])));
        }
        $this->stdout("  Campaigns import PAUSED with no budget — set a budget and enable before serving.\n", Console::FG_YELLOW);

        return ExitCode::OK;
    }
}
