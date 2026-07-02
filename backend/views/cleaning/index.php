<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $summary */

$this->title = 'Cleaning';
$this->params['breadcrumbs'][] = $this->title;

$total = (int) $summary['total'];
$funnel = $summary['funnel'];
$reasons = $summary['reasons'];
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::beginForm(['run'], 'post') ?>
        <?= Html::submitButton(
            $summary['hasRun'] ? 'Re-run cleaning' : 'Run cleaning',
            ['class' => 'btn btn-primary'],
        ) ?>
    <?= Html::endForm() ?>
</div>
<p class="text-muted mt-2">
    Filter out the keywords that aren't worth advertising — junk terms, duplicates, brand names,
    and searches with too little traffic. Nothing is deleted: every keyword you remove keeps a short
    note on why, so you can always check the decision. Adjust the
    <?= Html::a('rules', ['/rules/index']) ?> and run it again to update the results.
</p>

<?php if (!$summary['hasRun']): ?>
    <div class="alert alert-info">
        Nothing cleaned yet. <?= $total ?> keyword(s) imported —
        run cleaning to see what's worth keeping.
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Keywords at each step</div>
            <div class="card-body">
                <?php foreach ($funnel as $i => $step): ?>
                    <?php
                    $remaining = (int) $step['remaining'];
                    $pct = $total > 0 ? round($remaining / $total * 100) : 0;
                    $isLast = $i === count($funnel) - 1;
                    ?>
                    <div class="mb-3">
                        <div class="d-flex justify-content-between small">
                            <span<?= $isLast ? ' class="fw-bold"' : '' ?>>
                                <?= Html::encode($step['label']) ?>
                            </span>
                            <span class="text-muted">
                                <?= Yii::$app->formatter->asInteger($remaining) ?>
                                <?php if ((int) $step['dropped'] > 0): ?>
                                    <span class="text-danger">(−<?= (int) $step['dropped'] ?>)</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        <div class="progress" role="progressbar" aria-valuenow="<?= $pct ?>"
                             aria-valuemin="0" aria-valuemax="100" style="height: 1.25rem;">
                            <div class="progress-bar <?= $isLast ? 'bg-success' : '' ?>"
                                 style="width: <?= $pct ?>%"><?= $pct ?>%</div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <hr>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Kept for ads</span>
                    <strong class="text-success"><?= Yii::$app->formatter->asInteger($summary['survivors']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Removed</span>
                    <strong><?= Yii::$app->formatter->asInteger($summary['dropped']['total']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Why keywords were removed</div>
            <div class="card-body">
                <?php if ($reasons === []): ?>
                    <p class="text-muted fst-italic mb-0">Nothing removed yet.</p>
                <?php else: ?>
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                        <?php foreach ($reasons as $r): ?>
                            <tr>
                                <td>
                                    <?= Html::a(
                                        Html::encode($r['drop_reason']),
                                        ['/import/keywords', 'KeywordSearch' => ['drop_reason' => $r['drop_reason'], 'view' => 'dropped']],
                                        ['class' => 'text-decoration-none'],
                                    ) ?>
                                </td>
                                <td class="text-end"><span class="badge text-bg-secondary"><?= (int) $r['cnt'] ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<p class="mt-4">
    <?= Html::a('View all keywords →', ['/import/keywords', 'KeywordSearch' => ['view' => 'all']], [
        'class' => 'btn btn-outline-secondary btn-sm',
    ]) ?>
    <?= Html::a('View cleaned keywords →', ['/import/keywords', 'KeywordSearch' => ['view' => 'cleaned']], [
        'class' => 'btn btn-outline-success btn-sm',
    ]) ?>
</p>
