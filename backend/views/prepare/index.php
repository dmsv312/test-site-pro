<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $summary */

$this->title = 'Campaigns';
$this->params['breadcrumbs'][] = $this->title;

$candidates = (int) $summary['candidates'];
$funnel = $summary['funnel'];
$dropped = $summary['dropped'];
$grouping = $summary['grouping'];
/** @var array<string, mixed> $byLanguage */
$byLanguage = $grouping['byLanguage'];
$alreadyUsedReason = 'Already advertised in Google Ads';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::beginForm(['run'], 'post') ?>
        <?= Html::submitButton(
            $summary['hasRun'] ? 'Rebuild campaigns' : 'Build campaigns',
            ['class' => 'btn btn-primary', 'disabled' => $candidates === 0],
        ) ?>
    <?= Html::endForm() ?>
</div>
<p class="text-muted mt-2">
    Turn your cleaned keywords into export-ready Google Ads campaigns. This step removes keywords Site.pro
    <strong>already advertises on</strong> (so you're left with fresh opportunities) and any
    <?= Html::a('blocked', ['/rules/index']) ?> terms, then organizes the rest into one campaign per
    language, with ad groups grouped by theme.
</p>

<?php if ($candidates === 0): ?>
    <div class="alert alert-warning">
        No cleaned keywords yet. Run <?= Html::a('cleaning', ['/cleaning/index']) ?> first, then
        come back here.
    </div>
<?php elseif (!$summary['hasRun']): ?>
    <div class="alert alert-info">
        <?= $candidates ?> cleaned keyword(s) ready — build your campaigns.
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
                    $pct = $candidates > 0 ? round($remaining / $candidates * 100) : 0;
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
                    <span class="text-muted">Ready for campaigns</span>
                    <strong class="text-success"><?= Yii::$app->formatter->asInteger($summary['prepared']) ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted">Merged from duplicates</span>
                    <strong><?= Yii::$app->formatter->asInteger($summary['mergedGroups']) ?></strong>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header">Removed before campaigns</div>
            <div class="card-body">
                <?php if ($dropped['total'] === 0): ?>
                    <p class="text-muted fst-italic mb-0">
                        <?= $summary['hasRun'] ? 'Nothing removed — all your cleaned keywords are new.' : 'Nothing removed yet.' ?>
                    </p>
                <?php else: ?>
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                        <tr>
                            <td>
                                <?= Html::a(
                                    'Already advertised in Google Ads',
                                    ['/import/keywords', 'KeywordSearch' => ['drop_reason' => $alreadyUsedReason, 'view' => 'dropped']],
                                    ['class' => 'text-decoration-none'],
                                ) ?>
                            </td>
                            <td class="text-end"><span class="badge text-bg-secondary"><?= (int) $dropped['already_used'] ?></span></td>
                        </tr>
                        <tr>
                            <td>Blocked term</td>
                            <td class="text-end"><span class="badge text-bg-secondary"><?= (int) $dropped['forbidden'] ?></span></td>
                        </tr>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<h2 class="h4 mt-4">
    Campaigns
    <?php if ($grouping['adGroups'] > 0): ?>
        <small class="text-muted">
            <?= (int) $grouping['languages'] ?> language(s) ·
            <?= (int) $grouping['adGroups'] ?> ad group(s) ·
            <?= (int) $grouping['groupedKeywords'] ?> keyword(s)
        </small>
    <?php endif; ?>
</h2>

<?php if ($byLanguage === []): ?>
    <p class="text-muted fst-italic">No campaigns yet — build them above.</p>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($byLanguage as $language => $data): ?>
            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><?= Html::encode($data['campaign']) ?></span>
                        <span class="badge text-bg-primary"><?= (int) $data['keywords'] ?> kw</span>
                    </div>
                    <div class="card-body">
                        <p class="small mb-3">
                            Landing page:
                            <?= Html::a(
                                Html::encode($data['final_url']),
                                $data['final_url'],
                                ['target' => '_blank', 'rel' => 'noopener', 'class' => 'text-decoration-none'],
                            ) ?>
                        </p>
                        <table class="table table-sm align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Ad group</th>
                                    <th class="text-end">Keywords</th>
                                    <th>Sample terms</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($data['groups'] as $group): ?>
                                <?php
                                /** @var app\models\AdGroup $group */
                                $sample = $group->getKeywords()->select('raw_term')->limit(6)->column();
                                $more = (int) $group->keyword_count - count($sample);
                                ?>
                                <tr>
                                    <td class="fw-medium">
                                        <?= Html::a(
                                            Html::encode($group->theme),
                                            ['/import/keywords', 'KeywordSearch' => ['view' => 'prepared', 'ad_group_id' => $group->id]],
                                            ['class' => 'text-decoration-none', 'title' => 'View this ad group’s keywords'],
                                        ) ?>
                                    </td>
                                    <td class="text-end"><span class="badge text-bg-secondary"><?= (int) $group->keyword_count ?></span></td>
                                    <td class="small text-muted">
                                        <?= Html::encode(implode(', ', $sample)) ?><?= $more > 0 ? ' …+' . $more : '' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<p class="mt-4">
    <?= Html::a('View prepared keywords →', ['/import/keywords', 'KeywordSearch' => ['view' => 'prepared']], [
        'class' => 'btn btn-outline-success btn-sm',
    ]) ?>
</p>
