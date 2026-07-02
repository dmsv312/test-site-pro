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
/** @var array<string, int> $viewCounts per-view row counts for the tab badges */

$this->title = 'Keywords';
$this->params['breadcrumbs'][] = ['label' => 'Import & data', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;

// Pipeline views (the prominent control): each is a stage-aware lens on the one table. The tab
// badges double as the pipeline counts, so they reconcile with the Cleaning and Prepare funnels.
$viewTabs = [
    KeywordSearch::VIEW_ALL => ['All', 'Every keyword you\'ve imported.'],
    KeywordSearch::VIEW_CLEANED => ['Cleaned', 'Kept after cleaning — your ad candidates.'],
    KeywordSearch::VIEW_PREPARED => ['Prepared', 'Grouped into campaigns — ready for ads.'],
    KeywordSearch::VIEW_DROPPED => ['Removed', 'Removed along the way — see why in the last column.'],
];
$currentView = $searchModel->effectiveView();
[$currentLabel, $currentBlurb] = $viewTabs[$currentView];
// Preserve the column filters when switching view, but drop paging so you land on page 1.
$currentFilters = Yii::$app->request->queryParams['KeywordSearch'] ?? [];
unset($currentFilters['view']);
$adGroupId = $searchModel->ad_group_id;
$total = (int) ($dataProvider->totalCount);
?>
<h1><?= Html::encode($this->title) ?></h1>
<p class="text-muted">
    Every keyword you've imported, in one place. Use the tabs to follow it through the workflow, and
    the filters (source, language, volume, competition) to narrow the list. Nothing is deleted —
    removed keywords stay here, each with the reason why.
</p>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
    <div class="btn-group btn-group-sm" role="group" aria-label="Keyword views">
        <?php foreach ($viewTabs as $key => [$label, $blurb]): ?>
            <?= Html::a(
                $label . ' <span class="badge rounded-pill ' . ($currentView === $key ? 'text-bg-light' : 'text-bg-secondary') . '">' . (int) ($viewCounts[$key] ?? 0) . '</span>',
                ['keywords', 'KeywordSearch' => array_merge($currentFilters, ['view' => $key])],
                ['class' => 'btn ' . ($currentView === $key ? 'btn-primary' : 'btn-outline-primary'), 'title' => $blurb],
            ) ?>
        <?php endforeach; ?>
    </div>
    <?= Html::a('← Back to import', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</div>
<p class="text-muted small mb-3">
    <strong><?= Html::encode($currentLabel) ?></strong> — <?= Html::encode($currentBlurb) ?>
    Showing <?= Yii::$app->formatter->asInteger($total) ?> keyword(s).
    <?php if ($adGroupId !== null): ?>
        <span class="badge text-bg-info">ad group #<?= (int) $adGroupId ?></span>
        <?= Html::a('clear', ['keywords', 'KeywordSearch' => array_merge(array_diff_key($currentFilters, ['ad_group_id' => null]), ['view' => $currentView])], ['class' => 'ms-1']) ?>
    <?php endif; ?>
</p>

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
            'label' => 'Status',
            'filter' => false, // status is chosen via the view tabs above, not per-column
            'headerOptions' => ['style' => 'width:120px'],
            'value' => fn(Keyword $k): string => [
                Keyword::STAGE_IMPORTED => 'Imported',
                Keyword::STAGE_CLEANED => 'Cleaned',
                Keyword::STAGE_PREPARED => 'In a campaign',
                Keyword::STAGE_AD_READY => 'Ready for ads',
            ][$k->stage] ?? $k->stage,
        ],
        [
            'attribute' => 'drop_reason',
            'label' => 'Why removed',
            'value' => fn(Keyword $k): string => (string) ($k->drop_reason ?? '—'),
            'contentOptions' => ['class' => 'small text-muted'],
        ],
    ],
]) ?>
