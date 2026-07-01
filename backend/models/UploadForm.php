<?php

declare(strict_types=1);

namespace app\models;

use yii\base\Model;
use yii\web\UploadedFile;

/**
 * The admin upload form: pick a source, attach a CSV or JSON file.
 */
class UploadForm extends Model
{
    public string $source = '';
    public ?UploadedFile $file = null;

    public function rules(): array
    {
        return [
            ['source', 'required'],
            ['source', 'in', 'range' => Keyword::SOURCES],
            [
                'file',
                'file',
                'skipOnEmpty' => false,
                'extensions' => ['csv', 'json'],
                'checkExtensionByMimeType' => false,
                'maxSize' => 20 * 1024 * 1024,
            ],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'source' => 'Source',
            'file' => 'File (CSV or JSON)',
        ];
    }

    /** csv|json, derived from the uploaded file's extension. */
    public function format(): string
    {
        $ext = $this->file !== null ? strtolower((string) $this->file->extension) : '';

        return $ext === 'json' ? 'json' : 'csv';
    }
}
