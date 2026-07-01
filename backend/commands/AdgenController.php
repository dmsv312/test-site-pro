<?php

declare(strict_types=1);

namespace app\commands;

use app\services\adgen\AdGenerationService;
use yii\console\Controller;
use yii\console\ExitCode;

/**
 * Console entry point for stage-6 ad generation — the same {@see AdGenerationService} the admin area
 * uses, proving the logic is decoupled from the web layer.
 *
 *   yii adgen/run
 */
class AdgenController extends Controller
{
    /** Generate an ad for every ad group and print a summary. */
    public function actionRun(): int
    {
        $summary = AdGenerationService::create()->run();

        $this->stdout("Ad generation done.\n");
        $this->stdout(sprintf(
            "  %d ad(s) for %d ad group(s) across %d language(s), covering %d keyword(s).\n",
            $summary['generated'],
            $summary['adGroups'],
            $summary['languages'],
            $summary['keywordsCovered'],
        ));
        $this->stdout(sprintf(
            "  Source: %d stored, %d template. Invalid: %d.\n\n",
            $summary['byStored'],
            $summary['byTemplate'],
            $summary['invalid'],
        ));

        foreach ($summary['byLanguage'] as $language => $data) {
            $this->stdout(sprintf("  %s — %s\n", strtoupper((string) $language), $data['final_url']));
            foreach ($data['groups'] as $group) {
                $ad = $group->generatedAd;
                $headline = $ad !== null ? ($ad->getHeadlines()[0] ?? '') : '(no ad)';
                $this->stdout(sprintf(
                    "    %-24s %-9s %s\n",
                    mb_strimwidth($group->theme, 0, 24),
                    $ad !== null ? '[' . $ad->generated_by . ']' : '',
                    $headline,
                ));
            }
        }

        return ExitCode::OK;
    }
}
