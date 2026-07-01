<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Cleaning;

use app\services\cleaning\VolumeRule;

final class VolumeRuleTest extends \Codeception\Test\Unit
{
    private VolumeRule $rule;

    protected function _before(): void
    {
        $this->rule = new VolumeRule(minVolume: 50);
    }

    public function testKeepsUnknownVolume(): void
    {
        // Null = the source gave no volume (e.g. Search Console). Can't judge → keep.
        verify($this->rule->reason(null))->null();
    }

    public function testDropsBelowThreshold(): void
    {
        verify($this->rule->reason(10))->stringContainsString('below volume');
        verify($this->rule->reason(0))->stringContainsString('below volume');
    }

    public function testKeepsAtOrAboveThreshold(): void
    {
        verify($this->rule->reason(50))->null();
        verify($this->rule->reason(1000))->null();
    }
}
