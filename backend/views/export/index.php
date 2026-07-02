<?php

declare(strict_types=1);

use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $summary */

$this->title = 'Export';
$this->params['breadcrumbs'][] = $this->title;

$ready = (bool) $summary['ready'];
/** @var array<string, mixed> $byLanguage */
$byLanguage = $summary['byLanguage'];
?>
<h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
<p class="text-muted mt-2">
    Download your campaigns ready to import into Google Ads — one campaign per language, its ad groups,
    every keyword (match type <strong><?= Html::encode((string) $summary['matchType']) ?></strong>), and
    one ad per group pointing at the right landing page. The download always reflects your latest
    <?= Html::a('campaigns', ['/prepare/index']) ?> and <?= Html::a('ads', ['/ads/index']) ?>.
    Google Ads offers <strong>two</strong> ways to import, so pick the file that matches how you work.
</p>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="card h-100 border-primary-subtle">
            <div class="card-body d-flex flex-column">
                <h2 class="h5 card-title">Google Ads Editor <span class="text-muted fw-normal">(desktop app)</span></h2>
                <p class="card-text small mb-2">
                    One <code>.csv</code> file with your keywords and ads together — the format the
                    desktop Google Ads Editor imports.
                </p>
                <p class="card-text small text-muted mb-3">
                    Import: <strong>Account → Import → From file</strong>, then review and post.
                </p>
                <div class="mt-auto">
                    <?= Html::a(
                        'Download Editor CSV',
                        ['download'],
                        ['class' => 'btn btn-primary' . ($ready ? '' : ' disabled'), 'aria-disabled' => $ready ? null : 'true'],
                    ) ?>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card h-100">
            <div class="card-body d-flex flex-column">
                <h2 class="h5 card-title">Google Ads website <span class="text-muted fw-normal">(browser)</span></h2>
                <p class="card-text small mb-2">
                    A <code>.zip</code> with a separate sheet for campaigns, ad groups, keywords, and
                    ads — the format the Google Ads website imports. Campaigns arrive <strong>paused,
                    without a budget</strong>, so nothing spends until you set one and turn them on.
                </p>
                <p class="card-text small text-muted mb-3">
                    Import: <strong>Tools → Bulk actions → Uploads</strong>, one sheet at a time, in the
                    order in the bundled README.
                </p>
                <div class="mt-auto">
                    <?= Html::a(
                        'Download bulk-upload ZIP',
                        ['download-bulk'],
                        ['class' => 'btn btn-outline-primary' . ($ready ? '' : ' disabled'), 'aria-disabled' => $ready ? null : 'true'],
                    ) ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($summary['adGroups'] === 0): ?>
    <div class="alert alert-warning">
        No campaigns yet. <?= Html::a('Build your campaigns', ['/prepare/index']) ?> and
        <?= Html::a('create ads', ['/ads/index']) ?> first, then export.
    </div>
<?php elseif (!$ready): ?>
    <div class="alert alert-info">
        <?= (int) $summary['adGroups'] ?> ad group(s) ready, but no ads yet —
        <?= Html::a('create ads', ['/ads/index']) ?> before exporting.
    </div>
<?php endif; ?>

<div class="row g-3 mb-2">
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body text-center">
            <div class="display-6"><?= (int) $summary['campaigns'] ?></div>
            <div class="text-muted small">campaigns (languages)</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body text-center">
            <div class="display-6"><?= (int) $summary['adGroups'] ?></div>
            <div class="text-muted small">ad groups</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body text-center">
            <div class="display-6"><?= (int) $summary['keywordRows'] ?></div>
            <div class="text-muted small">keywords</div>
        </div></div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="card h-100"><div class="card-body text-center">
            <div class="display-6 <?= $ready ? 'text-success' : '' ?>"><?= (int) $summary['adRows'] ?></div>
            <div class="text-muted small">responsive search ads</div>
        </div></div>
    </div>
</div>

<?php if ($summary['groupsWithoutAd'] > 0): ?>
    <div class="alert alert-warning py-2 small">
        <?= (int) $summary['groupsWithoutAd'] ?> ad group(s) don't have a usable ad yet
        <?= $summary['invalidAds'] > 0 ? '(' . (int) $summary['invalidAds'] . " didn't pass checks) " : '' ?>—
        their keywords still export, just without an ad.
        <?= Html::a('Create ads', ['/ads/index']) ?> again to fill the gaps.
    </div>
<?php endif; ?>

<?php foreach ($byLanguage as $language => $data): ?>
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span>
                <?= Html::encode($data['campaign']) ?>
                <span class="badge text-bg-secondary ms-1"><?= (int) $data['keywords'] ?> kw</span>
                <span class="badge text-bg-<?= $data['ads'] === count($data['groups']) ? 'success' : 'warning' ?> ms-1">
                    <?= (int) $data['ads'] ?>/<?= count($data['groups']) ?> ads
                </span>
            </span>
            <?= Html::a(
                Html::encode($data['final_url']),
                $data['final_url'],
                ['target' => '_blank', 'rel' => 'noopener', 'class' => 'small text-decoration-none'],
            ) ?>
        </div>
        <div class="card-body">
            <table class="table table-sm align-middle mb-0">
                <thead>
                    <tr>
                        <th>Ad group</th>
                        <th class="text-end">Keywords</th>
                        <th>Ad</th>
                        <th>First headline</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($data['groups'] as $group): ?>
                    <?php
                    /** @var app\models\AdGroup $group */
                    $ad = $group->generatedAd;
                    $valid = $ad !== null && $ad->is_valid;
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
                        <td>
                            <?php if ($valid): ?>
                                <?php $isCurated = $ad->generated_by === app\models\GeneratedAd::BY_STORED; ?>
                                <span class="badge text-bg-<?= $isCurated ? 'primary' : 'light' ?>">
                                    <?= $isCurated ? 'Curated' : 'Template' ?>
                                </span>
                            <?php elseif ($ad !== null): ?>
                                <span class="badge text-bg-danger">Needs attention</span>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="small text-muted">
                            <?= $valid ? Html::encode($ad->getHeadlines()[0] ?? '') : '' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($ready): ?>
    <div class="d-flex flex-wrap gap-2 mt-4">
        <?= Html::a(
            'Download Editor CSV (' . (int) $summary['totalRows'] . ' rows) →',
            ['download'],
            ['class' => 'btn btn-outline-success'],
        ) ?>
        <?= Html::a(
            'Download bulk-upload ZIP (4 sheets) →',
            ['download-bulk'],
            ['class' => 'btn btn-outline-success'],
        ) ?>
    </div>
<?php endif; ?>
