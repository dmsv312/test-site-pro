<?php

declare(strict_types=1);

namespace app\commands;

use app\services\cleaning\CleaningService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Console entry point for the cleaning pipeline — the same {@see CleaningService} the admin
 * area uses, proving the logic is decoupled from the web layer.
 *
 *   yii clean/run
 */
class CleanController extends Controller
{
    /** Run cleaning over all keywords and print the resulting funnel. */
    public function actionRun(): int
    {
        $summary = CleaningService::create()->run();
        $dropped = $summary['dropped'];

        $this->stdout("Cleaning done.\n");
        foreach ($summary['funnel'] as $step) {
            $this->stdout(sprintf(
                "  %-24s %6d  %s\n",
                $step['label'],
                (int) $step['remaining'],
                (int) $step['dropped'] > 0 ? '(-' . (int) $step['dropped'] . ')' : '',
            ));
        }
        $this->stdout(sprintf(
            "\nKept %d of %d. Dropped: %d junk, %d duplicate, %d brand, %d below volume.\n",
            $summary['survivors'],
            $summary['total'],
            $dropped['junk'],
            $dropped['duplicate'],
            $dropped['brand'],
            $dropped['below_volume'],
        ));
        $this->stdout("Downstream stages were reset — run `yii prepare/run` to rebuild stage 5.\n");

        return ExitCode::OK;
    }
}
