<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Cleaning;

use app\services\cleaning\JunkRule;

final class JunkRuleTest extends \Codeception\Test\Unit
{
    private JunkRule $rule;

    protected function _before(): void
    {
        $this->rule = new JunkRule(maxTermLength: 80);
    }

    public function testKeepsRealKeywords(): void
    {
        verify($this->rule->reason('website builder'))->null();
        verify($this->rule->reason('best online store'))->null();
        // 5+ letter words with clustered consonants must survive (guards the gibberish check).
        verify($this->rule->reason('strength'))->null();
        verify($this->rule->reason('durchschnitt'))->null(); // German, real word, long cluster
    }

    public function testSingleCharacter(): void
    {
        verify($this->rule->reason('a'))->stringContainsString('single character');
    }

    public function testDigitsOnly(): void
    {
        verify($this->rule->reason('123456'))->stringContainsString('digits only');
        verify($this->rule->reason('12 50'))->stringContainsString('digits only');
    }

    public function testSymbolsOnly(): void
    {
        verify($this->rule->reason('!!!'))->stringContainsString('symbols only');
        verify($this->rule->reason('@ # $'))->stringContainsString('symbols only');
    }

    public function testTooLong(): void
    {
        verify($this->rule->reason(str_repeat('x', 81)))->stringContainsString('too long');
        // Exactly at the limit is fine (but a single repeated char is not a vowel-free short token).
        verify($this->rule->reason('word ' . str_repeat('a', 75)))->null();
    }

    public function testStopwordOnly(): void
    {
        verify($this->rule->reason('the'))->stringContainsString('stopword only');
        verify($this->rule->reason('for the'))->stringContainsString('stopword only');
        // A stopword mixed with a real token is kept.
        verify($this->rule->reason('builder for'))->null();
    }

    public function testGibberish(): void
    {
        // The planted keyboard-mash term (normalized, token-sorted) — caught via "zxcvbnm".
        verify($this->rule->reason('asdfghjkl qwerty zxcvbnm'))->stringContainsString('gibberish');
        verify($this->rule->reason('zxcvbnm'))->stringContainsString('gibberish');
    }

    public function testGibberishIsNarrow(): void
    {
        // Documented limitation: a 5+ letter token that still has a vowel is NOT called gibberish,
        // so pure keyboard rows containing a vowel (e.g. "asdfghjkl") survive on their own.
        verify($this->rule->reason('asdfghjkl'))->null();
        // Short vowel-free tech abbreviations (< 5 chars) are safe.
        verify($this->rule->reason('html css'))->null();
    }
}
