<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Adgen;

use app\services\adgen\RsaValidator;
use app\services\adgen\TemplateAdGenerator;

final class TemplateAdGeneratorTest extends \Codeception\Test\Unit
{
    private TemplateAdGenerator $generator;
    private RsaValidator $validator;

    protected function _before(): void
    {
        $this->generator = new TemplateAdGenerator();
        $this->validator = new RsaValidator();
    }

    /** @return string[] */
    private function languages(): array
    {
        return ['en', 'de', 'es', 'fr', 'it', 'pt'];
    }

    public function testEveryLanguageProducesAValidAd(): void
    {
        foreach ($this->languages() as $lang) {
            $ad = $this->generator->generate($lang, 'Website', 'website');

            verify($this->validator->validate($ad))->equals([]);
            verify($ad->language)->equals($lang);
            verify(count($ad->headlines) >= RsaValidator::MIN_HEADLINES)->true();
            verify(count($ad->descriptions) >= RsaValidator::MIN_DESCRIPTIONS)->true();
        }
    }

    public function testThemeIsWovenIntoHeadlines(): void
    {
        $ad = $this->generator->generate('en', 'Builder', 'builder');

        verify(in_array('Builder', $ad->headlines, true))->true();
    }

    public function testGeneralThemeAddsNoThemeHeadlineButStaysValid(): void
    {
        $ad = $this->generator->generate('en', 'General', 'general');

        verify(in_array('General', $ad->headlines, true))->false();
        verify($this->validator->isValid($ad))->true();
    }

    public function testUnknownLanguageFallsBackToEnglish(): void
    {
        $ad = $this->generator->generate('zz', 'Website', 'website');

        verify($ad->language)->equals('en');
        verify($this->validator->isValid($ad))->true();
    }

    public function testDeterministic(): void
    {
        $a = $this->generator->generate('fr', 'Créer', 'créer');
        $b = $this->generator->generate('fr', 'Créer', 'créer');

        verify($a->headlines)->equals($b->headlines);
        verify($a->descriptions)->equals($b->descriptions);
    }

    public function testHeadlineCeilingRespected(): void
    {
        $ad = $this->generator->generate('en', 'Website', 'website');

        verify(count($ad->headlines) <= RsaValidator::MAX_HEADLINES)->true();
        verify(count($ad->descriptions) <= RsaValidator::MAX_DESCRIPTIONS)->true();
    }

    public function testControlCharThemeStillProducesValidAd(): void
    {
        // A theme carrying a stray control byte (e.g. an unclean source keyword) must not yield a
        // headline that fails RsaValidator — the theme-derived headlines are dropped and the ad
        // falls back to the (always-clean) language pool.
        $ad = $this->generator->generate('en', "Web\x07builder", "web\x07builder");

        verify($this->validator->validate($ad))->equals([]);
        verify(count($ad->headlines) >= RsaValidator::MIN_HEADLINES)->true();
    }
}
