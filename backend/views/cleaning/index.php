<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $summary */

$this->title = 'Cleaning funnel';
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
    Cleaning flags keywords in sequence — junk → duplicates → brand → low volume — and records
    why each was dropped, without deleting anything. Editing the
    <?= Html::a('rules', ['/rules/index']) ?> and re-running recomputes this funnel.
</p>

<?php if (!$summary['hasRun']): ?>
    <div class="alert alert-info">
        Cleaning hasn’t run yet. <?= $total ?> keyword(s) imported —
        press “Run cleaning” to score them.
    </div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-7">
        <div class="card h-100">
            <div class="card-header">Funnel</div>
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
                    <span class="text-muted">Kept (ad-candidate keywords)</span>
                    <strong class="text-success"><?= Yii::$app->formatter->asInteger($summary['survivors']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Dropped</span>
                    <strong><?= Yii::$app->formatter->asInteger($summary['dropped']['total']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Why keywords were dropped</div>
            <div class="card-body">
                <?php if ($reasons === []): ?>
                    <p class="text-muted fst-italic mb-0">Nothing dropped yet.</p>
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
