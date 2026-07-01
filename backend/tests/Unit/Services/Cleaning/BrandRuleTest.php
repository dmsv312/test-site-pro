<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Cleaning;

use app\services\cleaning\BrandRule;

final class BrandRuleTest extends \Codeception\Test\Unit
{
    private BrandRule $rule;

    protected function _before(): void
    {
        $this->rule = new BrandRule(['site.pro', 'sitepro', 'wix', 'squarespace', 'tilda']);
    }

    public function testMatchesBrandToken(): void
    {
        verify($this->rule->reason('templates wix'))->stringContainsString('wix');
        verify($this->rule->reason('pricing squarespace'))->stringContainsString('squarespace');
        verify($this->rule->reason('login site.pro'))->stringContainsString('site.pro');
    }

    public function testMatchesAtPunctuationBoundary(): void
    {
        // A brand followed by punctuation (not a letter/digit) is still a brand match.
        verify($this->rule->reason('review wix.com'))->stringContainsString('wix');
    }

    public function testDoesNotMatchInsideAWord(): void
    {
        // The brand as a mere substring of a longer real word must NOT match (Spanish "tildar" /
        // "matilda"; "wixel"). This is the false-positive class the boundary check prevents.
        verify($this->rule->reason('tildar acento'))->null();
        verify($this->rule->reason('matilda'))->null();
        verify($this->rule->reason('wixel gadget'))->null();
    }

    public function testKeepsNonBrand(): void
    {
        verify($this->rule->reason('best website builder'))->null();
        verify($this->rule->reason('online store maker'))->null();
    }

    public function testEmptyBrandTermIsIgnored(): void
    {
        $rule = new BrandRule(['', 'wix']);
        verify($rule->reason('cheap website'))->null();
        verify($rule->reason('wix pricing'))->stringContainsString('wix');
    }
}
