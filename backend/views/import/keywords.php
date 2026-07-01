<?php

declare(strict_types=1);

use app\models\Keyword;
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
?>
<h1><?= Html::encode($this->title) ?></h1>
<p class="text-muted">
    Every imported keyword, normalized into one table. Filter by source, language, stage, or
    minimum volume; sort by any column. Cleaning and preparation (next stages) will flag rows
    here with a reason rather than deleting them.
</p>

<p>
    <?= Html::a('← Back to import', ['index'], ['class' => 'btn btn-outline-secondary btn-sm']) ?>
</p>

<?= GridView::widget([
    'dataProvider' => $dataProvider,
    'filterModel' => $searchModel,
    'tableOptions' => ['class' => 'table table-striped table-bordered table-sm align-middle'],
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
    ],
]) ?>
