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

    /**
     * The uploaded file is bound from `$_FILES` via {@see \yii\web\UploadedFile::getInstance()}, never
     * mass-assigned. `Html::activeFileInput()` renders a hidden empty `file` field, so a real browser
     * submit carries `file=""` in the POST body; letting that empty string be assigned to the typed
     * `?UploadedFile` property would throw a `TypeError`. So drop `file` from the data before the
     * normal mass assignment — the controller sets it from `$_FILES` afterwards.
     *
     * @param array<string, mixed> $data
     */
    public function load($data, $formName = null): bool
    {
        $scope = $formName ?? $this->formName();
        if ($scope === '') {
            unset($data['file']);
        } elseif (isset($data[$scope]) && is_array($data[$scope])) {
            unset($data[$scope]['file']);
        }

        return parent::load($data, $formName);
    }

    /** csv|json, derived from the uploaded file's extension. */
    public function format(): string
    {
        $ext = $this->file !== null ? strtolower((string) $this->file->extension) : '';

        return $ext === 'json' ? 'json' : 'csv';
    }
}
