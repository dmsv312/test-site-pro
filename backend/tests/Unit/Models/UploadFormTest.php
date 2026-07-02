<?php

declare(strict_types=1);

namespace app\tests\Unit\Models;

use app\models\UploadForm;

final class UploadFormTest extends \Codeception\Test\Unit
{
    /**
     * Regression: a real browser submit carries `file=""` (Html::activeFileInput renders a hidden
     * empty field), and load() must not assign that empty string to the typed ?UploadedFile property
     * — doing so threw a TypeError and 500'd the upload. load() drops the file key; the actual file
     * is bound from $_FILES in the controller afterwards.
     */
    public function testLoadIgnoresEmptyFileFieldFromHiddenInput(): void
    {
        $model = new UploadForm();

        $loaded = $model->load(['UploadForm' => ['source' => 'google_ads', 'file' => '']]);

        verify($loaded)->true();
        verify($model->source)->equals('google_ads');
        verify($model->file)->null();   // untouched — bound later via UploadedFile::getInstance()
    }

    public function testLoadStillPopulatesSourceWhenNoFileKeyPresent(): void
    {
        $model = new UploadForm();

        verify($model->load(['UploadForm' => ['source' => 'ahrefs_paid']]))->true();
        verify($model->source)->equals('ahrefs_paid');
    }

    public function testFormatIsDerivedAndDefaultsToCsv(): void
    {
        $model = new UploadForm();

        // No file bound → the default branch is csv (never touches a null file's ->extension).
        verify($model->format())->equals('csv');
    }
}
