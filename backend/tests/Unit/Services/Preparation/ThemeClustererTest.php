<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Preparation;

use app\services\preparation\ThemeClusterer;

final class ThemeClustererTest extends \Codeception\Test\Unit
{
    private ThemeClusterer $clusterer;

    protected function _before(): void
    {
        $this->clusterer = new ThemeClusterer();
    }

    public function testDominantTokenBecomesTheme(): void
    {
        // "website" appears in every keyword → the dominant token, so it names the ad group.
        $assign = $this->clusterer->assign([
            1 => 'builder website',
            2 => 'free website',
            3 => 'website',
        ]);

        verify($assign[1]['theme_key'])->equals('website');
        verify($assign[2]['theme_key'])->equals('website');
        verify($assign[3]['theme_key'])->equals('website');
        verify($assign[1]['theme'])->equals('Website');
    }

    public function testTieBrokenAlphabetically(): void
    {
        // "alpha" and "beta" each appear twice; a keyword holding both takes the alphabetical first.
        $assign = $this->clusterer->assign([
            1 => 'alpha beta',
            2 => 'alpha',
            3 => 'beta',
        ]);

        verify($assign[1]['theme_key'])->equals('alpha');
    }

    public function testStopwordsAndBareNumbersIgnored(): void
    {
        // Multilingual function words never win as a theme; only the content word "web" does.
        $assign = $this->clusterer->assign([
            1 => 'de la web',
            2 => 'web gratis',
            3 => 'web',
        ]);

        verify($assign[1]['theme_key'])->equals('web');
    }

    public function testSingletonThemeCollapsesToGeneral(): void
    {
        // A keyword whose only tokens are each unique folds into the general bucket.
        $assign = $this->clusterer->assign([
            1 => 'web site',
            2 => 'web site online',
            3 => 'unique standalone phrase',
        ]);

        verify($assign[3]['theme_key'])->equals(ThemeClusterer::GENERAL_KEY);
        verify($assign[3]['theme'])->equals(ThemeClusterer::GENERAL_LABEL);
    }

    public function testAllStopwordKeywordGoesToGeneral(): void
    {
        $assign = $this->clusterer->assign([
            1 => 'de la',
            2 => 'web site builder',
            3 => 'web site builder online',
        ]);

        verify($assign[1]['theme_key'])->equals(ThemeClusterer::GENERAL_KEY);
    }
}
