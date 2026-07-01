<?php

declare(strict_types=1);

namespace app\controllers;

use Yii;
use app\models\ImportBatch;
use app\models\Keyword;
use app\models\KeywordSearch;
use app\models\UploadForm;
use app\services\import\ImportService;
use yii\data\ActiveDataProvider;
use yii\filters\AccessControl;
use yii\filters\VerbFilter;
use yii\helpers\FileHelper;
use yii\web\Controller;
use yii\web\Response;
use yii\web\UploadedFile;

/**
 * Admin area (login-gated): upload source files, browse import history, and inspect every
 * imported keyword. Thin controller — all import logic lives in {@see ImportService}.
 */
class ImportController extends Controller
{
    public function behaviors(): array
    {
        return [
            'access' => [
                'class' => AccessControl::class,
                'rules' => [
                    ['allow' => true, 'roles' => ['@']],
                ],
            ],
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => [
                    'upload' => ['post'],
                    'clear' => ['post'],
                ],
            ],
        ];
    }

    /** Import history + a per-source summary of what has been imported. */
    public function actionIndex(): string
    {
        $batches = new ActiveDataProvider([
            'query' => ImportBatch::find()->orderBy(['created_at' => SORT_DESC, 'id' => SORT_DESC]),
            'pagination' => ['pageSize' => 20],
        ]);

        $summary = ImportBatch::getDb()->createCommand(
            'SELECT source, COUNT(*) AS cnt FROM ' . Keyword::tableName() . ' GROUP BY source',
        )->queryAll();
        $counts = array_column($summary, 'cnt', 'source');

        $total = (int) Keyword::find()->count();

        return $this->render('index', [
            'batches' => $batches,
            'counts' => $counts,
            'total' => $total,
            'model' => new UploadForm(),
        ]);
    }

    /** Handle a file upload → run the import → redirect to the imported batch's keywords. */
    public function actionUpload(): Response
    {
        $model = new UploadForm();
        $model->load(Yii::$app->request->post());
        $model->file = UploadedFile::getInstance($model, 'file');

        if (!$model->validate()) {
            $errors = implode(' ', array_merge(...array_values($model->getErrors())));
            Yii::$app->session->setFlash('error', 'Upload rejected: ' . $errors);

            return $this->redirect(['index']);
        }

        $tmp = $this->stashUpload($model->file);
        try {
            $batch = ImportService::create()->import(
                $model->source,
                $model->format(),
                $tmp,
                $model->file->name,
            );
        } finally {
            @unlink($tmp);
        }

        if ($batch->status === ImportBatch::STATUS_IMPORTED) {
            Yii::$app->session->setFlash(
                'success',
                "Imported {$batch->rows_imported} keyword(s) from “{$batch->filename}”"
                . " (skipped {$batch->rows_skipped}).",
            );

            return $this->redirect(['keywords', 'KeywordSearch' => ['batch_id' => $batch->id, 'view' => 'all']]);
        }

        Yii::$app->session->setFlash('error', "Import failed: {$batch->message}");

        return $this->redirect(['index']);
    }

    /** The full keyword table with filters, sorting, and pagination. */
    public function actionKeywords(): string
    {
        $searchModel = new KeywordSearch();
        $dataProvider = $searchModel->search(Yii::$app->request->queryParams);

        $languages = Keyword::find()
            ->select('language')
            ->distinct()
            ->where(['not', ['language' => null]])
            ->orderBy('language')
            ->column();

        return $this->render('keywords', [
            'searchModel' => $searchModel,
            'dataProvider' => $dataProvider,
            'languages' => array_combine($languages, $languages),
            'viewCounts' => KeywordSearch::counts(),
        ]);
    }

    /** Wipe all imported data (handy for re-importing during a demo). */
    public function actionClear(): Response
    {
        $db = Yii::$app->db;
        $db->createCommand()->delete(Keyword::tableName())->execute();
        $db->createCommand()->delete(ImportBatch::tableName())->execute();
        Yii::$app->session->setFlash('success', 'All imported data cleared.');

        return $this->redirect(['index']);
    }

    /** Move the PHP upload into a private runtime path we control, then hand it to the service. */
    private function stashUpload(UploadedFile $file): string
    {
        $dir = Yii::getAlias('@runtime/imports');
        FileHelper::createDirectory($dir);
        $path = $dir . '/' . uniqid('imp_', true) . '.' . ($file->extension ?: 'dat');

        if (!$file->saveAs($path)) {
            throw new \RuntimeException('Could not store the uploaded file.');
        }

        return $path;
    }
}
