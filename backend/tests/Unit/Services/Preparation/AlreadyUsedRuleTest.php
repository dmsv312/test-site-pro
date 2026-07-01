<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Preparation;

use app\services\preparation\AlreadyUsedRule;

final class AlreadyUsedRuleTest extends \Codeception\Test\Unit
{
    private AlreadyUsedRule $rule;

    protected function _before(): void
    {
        // The used-set is the normalized terms already running in the google_ads source.
        $this->rule = new AlreadyUsedRule(['website builder', 'free website', 'online store']);
    }

    public function testFlagsExactUsedTerm(): void
    {
        verify($this->rule->reason('website builder'))->stringContainsString('already used');
        verify($this->rule->reason('free website'))->stringContainsString('already used');
    }

    public function testExactMatchOnly(): void
    {
        // "Already used" is the same search term, not a superset/subset — it must not match a
        // different term that merely shares words (that is the dedup/normalization concern).
        verify($this->rule->reason('website'))->null();
        verify($this->rule->reason('cheap website builder pro'))->null();
    }

    public function testKeepsNetNewTerm(): void
    {
        verify($this->rule->reason('best ecommerce platform'))->null();
    }

    public function testEmptyUsedTermIsIgnored(): void
    {
        $rule = new AlreadyUsedRule(['', 'free website']);
        verify($rule->reason(''))->null();
        verify($rule->reason('free website'))->stringContainsString('already used');
    }
}
