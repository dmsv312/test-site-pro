<?php

declare(strict_types=1);

namespace app\tests\Unit\Services\Adgen;

use app\services\adgen\StoredAdSource;

final class StoredAdSourceTest extends \Codeception\Test\Unit
{
    private function entries(): array
    {
        return [
            'en:builder' => [
                'headlines' => ['Website Builder', 'Build Your Site', 'Start Free'],
                'descriptions' => ['Build a professional website in minutes.', 'No coding needed.'],
                'path1' => 'builder',
                'path2' => 'free',
            ],
            'en:broken' => 'not-an-array',
        ];
    }

    public function testReturnsContentForKnownKey(): void
    {
        $source = new StoredAdSource($this->entries());
        $ad = $source->get('en', 'builder');

        verify($ad)->notNull();
        verify($ad->language)->equals('en');
        verify($ad->headlines)->equals(['Website Builder', 'Build Your Site', 'Start Free']);
        verify($ad->path1)->equals('builder');
        verify($ad->path2)->equals('free');
    }

    public function testReturnsNullForUnknownKey(): void
    {
        $source = new StoredAdSource($this->entries());

        verify($source->get('de', 'builder'))->null();
        verify($source->get('en', 'missing'))->null();
    }

    public function testMalformedEntryYieldsNull(): void
    {
        $source = new StoredAdSource($this->entries());

        verify($source->get('en', 'broken'))->null();
    }

    public function testMissingFileMeansNoStoredCopy(): void
    {
        $source = StoredAdSource::fromFile('/no/such/generated-ads.json');

        verify($source->get('en', 'builder'))->null();
    }

    public function testLoadsFromFile(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'ads') . '.json';
        file_put_contents($path, json_encode($this->entries()));

        $source = StoredAdSource::fromFile($path);
        verify($source->get('en', 'builder'))->notNull();
        verify($source->get('en', 'builder')->descriptions)
            ->equals(['Build a professional website in minutes.', 'No coding needed.']);

        unlink($path);
    }
}
