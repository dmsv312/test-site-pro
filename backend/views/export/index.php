<?php

declare(strict_types=1);

use app\services\export\GoogleAdsEditorExport;
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
    Both artifacts recreate the same campaign tree — one campaign per language, its themed ad groups,
    every prepared keyword (match type <strong><?= Html::encode((string) $summary['matchType']) ?></strong>),
    and one responsive search ad per ad group, each pointing at its verified localized target URL. Like
    the rest of the pipeline they are derived on demand from the current state, so they always reflect
    the latest <?= Html::a('preparation', ['/prepare/index']) ?> and
    <?= Html::a('ad generation', ['/ads/index']) ?>. Google Ads has <strong>two</strong> import paths and
    they use different file formats — pick the one matching the tool you'll use.
</p>

<div class="row g-3 mt-1">
    <div class="col-md-6">
        <div class="card h-100 border-primary-subtle">
            <div class="card-body d-flex flex-column">
                <h2 class="h5 card-title">Google Ads Editor <span class="text-muted fw-normal">(desktop app)</span></h2>
                <p class="card-text small mb-2">
                    A single combined <code>.csv</code> — keywords and ads in one file, the entity type
                    inferred from the columns each row fills. This is the desktop Editor's format.
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
                <h2 class="h5 card-title">Google Ads web UI <span class="text-muted fw-normal">(browser)</span></h2>
                <p class="card-text small mb-2">
                    A <code>.zip</code> of one sheet per entity — campaigns, ad groups, keywords, ads —
                    because the web tool has no combined format. Campaigns import <strong>paused,
                    without a budget</strong> (set one before enabling).
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
        No campaigns yet. Run <?= Html::a('preparation', ['/prepare/index']) ?>, then
        <?= Html::a('generate ads', ['/ads/index']) ?>, then export.
    </div>
<?php elseif (!$ready): ?>
    <div class="alert alert-info">
        <?= (int) $summary['adGroups'] ?> ad group(s) ready, but no ads generated yet — run
        <?= Html::a('ad generation', ['/ads/index']) ?> before exporting.
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
            <div class="text-muted small">keyword rows</div>
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
        <?= (int) $summary['groupsWithoutAd'] ?> ad group(s) have no valid ad
        <?= $summary['invalidAds'] > 0 ? '(' . (int) $summary['invalidAds'] . ' failed validation) ' : '' ?>—
        their keywords are still exported, but without an ad. Re-run
        <?= Html::a('ad generation', ['/ads/index']) ?> to fill them.
    </div>
<?php endif; ?>

<p class="text-muted small">
    Editor CSV layout — one row per entity, columns filled by type:
    <span class="badge text-bg-light border">keyword row</span>
    <code>Campaign · Ad Group · Keyword · Match Type · Final URL</code> ·
    <span class="badge text-bg-light border">ad row</span>
    <code>Campaign · Ad Group · Headline 1…<?= GoogleAdsEditorExport::MAX_HEADLINES ?>
        · Description 1…<?= GoogleAdsEditorExport::MAX_DESCRIPTIONS ?> · Path 1/2 · Final URL</code>
    (Editor infers the responsive search ad from the headline/description columns — no ad-type column).
    UTF-8, RFC-4180 quoting.
</p>

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
                                <span class="badge text-bg-<?= $ad->generated_by === app\models\GeneratedAd::BY_STORED ? 'primary' : 'light' ?>">
                                    <?= Html::encode($ad->generated_by) ?>
                                </span>
                            <?php elseif ($ad !== null): ?>
                                <span class="badge text-bg-danger">invalid</span>
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
