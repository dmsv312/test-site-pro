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
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::a(
        'Download Google Ads Editor CSV',
        ['download'],
        ['class' => 'btn btn-primary' . ($ready ? '' : ' disabled'), 'aria-disabled' => $ready ? null : 'true'],
    ) ?>
</div>
<p class="text-muted mt-2">
    The export is a single <strong>Google Ads Editor</strong>-compatible CSV that recreates the whole
    campaign tree: one campaign per language, its themed ad groups, every prepared keyword (match type
    <strong><?= Html::encode((string) $summary['matchType']) ?></strong>), and one responsive search ad
    per ad group — each pointing at its verified localized target URL. Like the rest of the pipeline the
    file is derived on demand from the current state, so it always reflects the latest
    <?= Html::a('preparation', ['/prepare/index']) ?> and <?= Html::a('ad generation', ['/ads/index']) ?>.
</p>

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
    File layout — one row per entity, columns filled by type:
    <span class="badge text-bg-light border">keyword row</span>
    <code>Campaign · Ad Group · Keyword · Match Type · Final URL</code> ·
    <span class="badge text-bg-light border">ad row</span>
    <code>Campaign · Ad Group · Ad Type · Headline 1…<?= GoogleAdsEditorExport::MAX_HEADLINES ?>
        · Description 1…<?= GoogleAdsEditorExport::MAX_DESCRIPTIONS ?> · Path 1/2 · Final URL</code>.
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
    <p class="mt-4">
        <?= Html::a(
            'Download Google Ads Editor CSV (' . (int) $summary['totalRows'] . ' rows) →',
            ['download'],
            ['class' => 'btn btn-outline-success'],
        ) ?>
    </p>
<?php endif; ?>
