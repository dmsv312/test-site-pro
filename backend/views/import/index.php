<?php

declare(strict_types=1);

use app\models\ImportBatch;
use app\models\Keyword;
use yii\bootstrap5\ActiveForm;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var yii\data\ActiveDataProvider $batches */
/** @var array<string, int|string> $counts per-source keyword counts */
/** @var int $total */
/** @var app\models\UploadForm $model */

$this->title = 'Import & data';
$this->params['breadcrumbs'][] = $this->title;
?>
<h1><?= Html::encode($this->title) ?></h1>
<p class="text-muted">
    Bring your keyword research together in one place. Upload exports from Google Ads, Search
    Console, or Ahrefs — as CSV or JSON — and review every keyword before it goes into a campaign.
</p>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header">Upload keywords</div>
            <div class="card-body">
                <?php $form = ActiveForm::begin([
                    'action' => ['upload'],
                    'options' => ['enctype' => 'multipart/form-data'],
                ]); ?>

                <?= $form->field($model, 'source')->dropDownList(
                    Keyword::SOURCE_LABELS,
                    ['prompt' => '— choose source —'],
                ) ?>

                <?= $form->field($model, 'file')->fileInput()->hint('CSV or JSON, up to 20 MB.') ?>

                <?= Html::submitButton('Import', ['class' => 'btn btn-primary']) ?>

                <?php ActiveForm::end(); ?>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span>Imported so far</span>
                <span class="badge text-bg-primary"><?= $total ?> keywords total</span>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach (Keyword::SOURCE_LABELS as $key => $label): ?>
                        <div class="col-6">
                            <div class="border rounded p-2 d-flex justify-content-between">
                                <span class="text-muted small"><?= Html::encode($label) ?></span>
                                <strong><?= (int) ($counts[$key] ?? 0) ?></strong>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="d-flex gap-2 mt-3 flex-wrap">
                    <?= Html::a('View all keywords', ['keywords', 'KeywordSearch' => ['view' => 'all']], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
                    <?= Html::a('Clear all data', ['clear'], [
                        'class' => 'btn btn-outline-danger btn-sm ms-auto',
                        'data' => [
                            'method' => 'post',
                            'confirm' => 'Delete all imported keywords and history?',
                        ],
                    ]) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<h2 class="h4 mt-5">Import history</h2>
<?= GridView::widget([
    'dataProvider' => $batches,
    'tableOptions' => ['class' => 'table table-striped table-bordered align-middle'],
    'emptyText' => 'No imports yet — upload a file above.',
    'columns' => [
        ['attribute' => 'id', 'headerOptions' => ['style' => 'width:60px']],
        [
            'attribute' => 'created_at',
            'label' => 'When',
            'value' => fn(ImportBatch $b): string => Yii::$app->formatter->asDatetime($b->created_at, 'short'),
        ],
        [
            'attribute' => 'source',
            'value' => fn(ImportBatch $b): string => $b->getSourceLabel(),
        ],
        'filename',
        [
            'attribute' => 'format',
            'value' => fn(ImportBatch $b): string => strtoupper($b->format),
        ],
        ['attribute' => 'rows_total', 'label' => 'Rows'],
        ['attribute' => 'rows_imported', 'label' => 'Imported'],
        ['attribute' => 'rows_skipped', 'label' => 'Skipped'],
        [
            'attribute' => 'status',
            'format' => 'raw',
            'value' => static function (ImportBatch $b): string {
                $class = $b->status === ImportBatch::STATUS_IMPORTED ? 'text-bg-success' : 'text-bg-danger';

                return Html::tag('span', Html::encode($b->status), ['class' => "badge {$class}"]);
            },
        ],
        [
            'attribute' => 'message',
            'value' => fn(ImportBatch $b): string => (string) $b->message,
            'contentOptions' => ['class' => 'small text-muted'],
        ],
        [
            'class' => yii\grid\ActionColumn::class,
            'template' => '{view}',
            'buttons' => [
                'view' => static fn($url, ImportBatch $b): string => Html::a(
                    'keywords',
                    ['keywords', 'KeywordSearch' => ['batch_id' => $b->id, 'view' => 'all']],
                    ['class' => 'btn btn-sm btn-outline-primary'],
                ),
            ],
        ],
    ],
]) ?>
