<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Preparation;

use app\services\preparation\ForbiddenRule;

final class ForbiddenRuleTest extends \Codeception\Test\Unit
{
    private ForbiddenRule $rule;

    protected function _before(): void
    {
        $this->rule = new ForbiddenRule(['casino', 'xxx']);
    }

    public function testMatchesForbiddenToken(): void
    {
        verify($this->rule->reason('free casino bonus'))->stringContainsString('casino');
        verify($this->rule->reason('xxx content'))->stringContainsString('xxx');
    }

    public function testDoesNotMatchInsideAWord(): void
    {
        // Word-boundary match, exactly like the brand rule: "casino" must not fire on "casinos".
        verify($this->rule->reason('casinos near me'))->null();
    }

    public function testKeepsCleanTerm(): void
    {
        verify($this->rule->reason('website builder'))->null();
    }

    public function testEmptyForbiddenTermIsIgnored(): void
    {
        $rule = new ForbiddenRule(['', 'xxx']);
        verify($rule->reason('cheap website'))->null();
        verify($rule->reason('xxx content'))->stringContainsString('xxx');
    }
}
