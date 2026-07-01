<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Adgen;

use app\services\adgen\AdContent;
use app\services\adgen\RsaValidator;

final class RsaValidatorTest extends \Codeception\Test\Unit
{
    private RsaValidator $validator;

    protected function _before(): void
    {
        $this->validator = new RsaValidator();
    }

    /** @param string[] $headlines @param string[] $descriptions */
    private function ad(array $headlines, array $descriptions, ?string $path1 = null, ?string $path2 = null): AdContent
    {
        return new AdContent('en', $headlines, $descriptions, $path1, $path2);
    }

    public function testValidAdPasses(): void
    {
        $ad = $this->ad(
            ['Build Your Website', 'Free Website Builder', 'Start Free Today'],
            ['Create a site in minutes.', 'No coding needed — start free.'],
        );

        verify($this->validator->validate($ad))->equals([]);
        verify($this->validator->isValid($ad))->true();
    }

    public function testTooFewHeadlinesFails(): void
    {
        $ad = $this->ad(['One', 'Two'], ['Desc one here.', 'Desc two here.']);

        verify($this->validator->isValid($ad))->false();
    }

    public function testTooManyHeadlinesFails(): void
    {
        $headlines = [];
        for ($i = 0; $i < 16; $i++) {
            $headlines[] = 'Headline ' . $i;
        }
        $ad = $this->ad($headlines, ['Desc one here.', 'Desc two here.']);

        verify($this->validator->isValid($ad))->false();
    }

    public function testOverLengthHeadlineFails(): void
    {
        $ad = $this->ad(
            [str_repeat('a', RsaValidator::HEADLINE_MAX + 1), 'Two', 'Three'],
            ['Desc one here.', 'Desc two here.'],
        );

        verify($this->validator->isValid($ad))->false();
    }

    public function testTooFewDescriptionsFails(): void
    {
        $ad = $this->ad(['One', 'Two', 'Three'], ['Only one description here.']);

        verify($this->validator->isValid($ad))->false();
    }

    public function testOverLengthDescriptionFails(): void
    {
        $ad = $this->ad(
            ['One', 'Two', 'Three'],
            [str_repeat('b', RsaValidator::DESCRIPTION_MAX + 1), 'Desc two here.'],
        );

        verify($this->validator->isValid($ad))->false();
    }

    public function testDuplicateHeadlineIsCaseInsensitive(): void
    {
        $ad = $this->ad(
            ['Build Site', 'build site', 'Third One'],
            ['Desc one here.', 'Desc two here.'],
        );

        verify($this->validator->isValid($ad))->false();
    }

    public function testBadPathsFail(): void
    {
        $tooLong = $this->ad(
            ['One', 'Two', 'Three'],
            ['Desc one here.', 'Desc two here.'],
            str_repeat('x', RsaValidator::PATH_MAX + 1),
        );
        verify($this->validator->isValid($tooLong))->false();

        $hasSpace = $this->ad(
            ['One', 'Two', 'Three'],
            ['Desc one here.', 'Desc two here.'],
            'web site',
        );
        verify($this->validator->isValid($hasSpace))->false();
    }

    public function testNullPathsAreFine(): void
    {
        $ad = $this->ad(['One', 'Two', 'Three'], ['Desc one here.', 'Desc two here.'], null, null);

        verify($this->validator->isValid($ad))->true();
    }

    public function testControlCharactersRejected(): void
    {
        $ad = $this->ad(
            ["Bad\x07Headline", 'Two', 'Three'],
            ['Desc one here.', 'Desc two here.'],
        );

        verify($this->validator->isValid($ad))->false();
    }
}
