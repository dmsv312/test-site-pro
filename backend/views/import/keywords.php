<?php

declare(strict_types=1);

use app\models\Keyword;
use app\models\KeywordSearch;
use yii\grid\GridView;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\KeywordSearch $searchModel */
/** @var yii\data\ActiveDataProvider $dataProvider */
/** @var array<string, string> $languages */

$this->title = 'Keywords';
$this->params['breadcrumbs'][] = ['label' => 'Import & data', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

$stageOptions = array_combine(Keyword::STAGES, Keyword::STAGES);

// Kept / Dropped / All toggle. Preserve the current filters (source, language, …) and reset paging.
$statusTabs = [
    KeywordSearch::STATUS_KEPT => 'Kept',
    KeywordSearch::STATUS_DROPPED => 'Dropped',
    KeywordSearch::STATUS_ALL => 'All',
];
$currentStatus = $searchModel->effectiveStatus();
$currentFilters = Yii::$app->request->queryParams['KeywordSearch'] ?? [];
?>
<h1><?= Html::encode($this->title) ?></h1>
<p class="text-muted">
    Every imported keyword, normalized into one table. Filter by source, language, stage,
    minimum volume, or drop reason; sort by any column. Cleaning flags rows here with a reason
    rather than deleting them — see the <?= Html::a('funnel', ['/cleaning/index']) ?>.
</p>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div class="btn-group btn-group-sm" role="group" aria-label="Keyword status">
        <?php foreach ($statusTabs as $key => $label): ?>
            <?= Html::a(
                $label,
                ['keywords', 'KeywordSearch' => array_merge($currentFilters, ['status' => $key])],
                ['class' => 'btn ' . ($currentStatus === $key ? 'btn-primary' : 'btn-outline-primary')],
            ) ?>
        <?php endforeach; ?>
    </div>
    <?= Html::a('← Back to import', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'tableOptions' => ['class' => 'table table-striped table-bordered table-sm align-middle'],
    'pager' => [
        'class' => yii\bootstrap5\LinkPager::class,
        'maxButtonCount' => 10,
    ],
    'columns' => [
        ['attribute' => 'id', 'headerOptions' => ['style' => 'width:70px']],
        [
            'attribute' => 'source',
            'filter' => Keyword::SOURCE_LABELS,
            'value' => fn(Keyword $k): string => $k->getSourceLabel(),
        ],
        [
            'attribute' => 'raw_term',
            'label' => 'Keyword',
        ],
        [
            'attribute' => 'language',
            'filter' => $languages,
            'headerOptions' => ['style' => 'width:90px'],
        ],
        [
            'attribute' => 'avg_monthly_searches',
            'label' => 'Volume',
            'filter' => Html::activeInput('number', $searchModel, 'minVolume', [
                'class' => 'form-control form-control-sm',
                'placeholder' => 'min',
            ]),
            'contentOptions' => ['class' => 'text-end'],
            'value' => fn(Keyword $k): string => $k->avg_monthly_searches !== null
                ? Yii::$app->formatter->asInteger($k->avg_monthly_searches)
                : '—',
        ],
        [
            'attribute' => 'cpc',
            'label' => 'CPC',
            'filter' => false,
            'contentOptions' => ['class' => 'text-end'],
            'value' => fn(Keyword $k): string => $k->cpc !== null ? '$' . $k->cpc : '—',
        ],
        [
            'attribute' => 'competition',
            'filter' => ['LOW' => 'LOW', 'MEDIUM' => 'MEDIUM', 'HIGH' => 'HIGH'],
            'value' => fn(Keyword $k): string => (string) ($k->competition ?? '—'),
        ],
        [
            'attribute' => 'competitor_domain',
            'label' => 'Competitor',
            'value' => fn(Keyword $k): string => (string) ($k->competitor_domain ?? '—'),
        ],
        [
            'attribute' => 'stage',
            'filter' => $stageOptions,
            'headerOptions' => ['style' => 'width:110px'],
        ],
        [
            'attribute' => 'drop_reason',
            'label' => 'Dropped — why',
            'value' => fn(Keyword $k): string => (string) ($k->drop_reason ?? '—'),
            'contentOptions' => ['class' => 'small text-muted'],
        ],
    ],
]) ?>
