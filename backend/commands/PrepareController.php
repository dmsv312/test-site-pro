<?php

declare(strict_types=1);

namespace app\commands;

use app\services\preparation\PreparationService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Console entry point for the preparation pipeline — the same {@see PreparationService} the admin
 * area uses, proving the logic is decoupled from the web layer.
 *
 *   yii prepare/run
 */
class PrepareController extends Controller
{
    /** Run preparation over the cleaned keywords and print the resulting funnel. */
    public function actionRun(): int
    {
        $summary = PreparationService::create()->run();
        $dropped = $summary['dropped'];

        $this->stdout("Preparation done.\n");
        foreach ($summary['funnel'] as $step) {
            $this->stdout(sprintf(
                "  %-28s %6d  %s\n",
                $step['label'],
                (int) $step['remaining'],
                (int) $step['dropped'] > 0 ? '(-' . (int) $step['dropped'] . ')' : '',
            ));
        }
        $this->stdout(sprintf(
            "\nPrepared %d of %d candidates. Dropped: %d already-used, %d forbidden. "
            . "Survivors represent %d merged duplicate group(s).\n",
            $summary['prepared'],
            $summary['candidates'],
            $dropped['already_used'],
            $dropped['forbidden'],
            $summary['mergedGroups'],
        ));

        $grouping = $summary['grouping'];
        $this->stdout(sprintf(
            "Grouped %d keyword(s) into %d ad group(s) across %d language(s):\n",
            $grouping['groupedKeywords'],
            $grouping['adGroups'],
            $grouping['languages'],
        ));
        foreach ($grouping['byLanguage'] as $language => $data) {
            $themes = array_map(
                static fn($g): string => $g->theme . ' (' . $g->keyword_count . ')',
                $data['groups'],
            );
            $this->stdout(sprintf(
                "  %-3s %2d kw → %s\n",
                strtoupper((string) $language),
                (int) $data['keywords'],
                implode(', ', $themes),
            ));
        }

        $this->stdout("\nAd generation was reset — run `yii adgen/run` to (re)generate the ads.\n");

        return ExitCode::OK;
    }
}
