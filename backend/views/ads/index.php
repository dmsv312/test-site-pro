<?php

declare(strict_types=1);

use app\services\adgen\RsaValidator;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var array<string, mixed> $summary */

$this->title = 'Ads';
$this->params['breadcrumbs'][] = $this->title;

$adGroups = (int) $summary['adGroups'];
/** @var array<string, mixed> $byLanguage */
$byLanguage = $summary['byLanguage'];

/** One headline/description with its live character count against the RSA limit. */
$chip = static function (string $text, int $max): string {
    $len = mb_strlen($text, 'UTF-8');
    $ok = $len <= $max;

    return '<li class="d-flex justify-content-between gap-3">'
        . '<span>' . Html::encode($text) . '</span>'
        . '<span class="text-' . ($ok ? 'muted' : 'danger') . ' small font-monospace">' . $len . '/' . $max . '</span>'
        . '</li>';
};
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
    <h1 class="mb-0"><?= Html::encode($this->title) ?></h1>
    <?= Html::beginForm(['run'], 'post') ?>
        <?= Html::submitButton(
            $summary['hasRun'] ? 'Recreate ads' : 'Create ads',
            ['class' => 'btn btn-primary', 'disabled' => $adGroups === 0],
        ) ?>
    <?= Html::endForm() ?>
</div>
<p class="text-muted mt-2">
    Create one responsive search ad for each ad group, written in the group's language and pointing
    to the right localized landing page. Every ad is checked against Google's length limits — up to
    <?= RsaValidator::HEADLINE_MAX ?> characters per headline and
    <?= RsaValidator::DESCRIPTION_MAX ?> per description — before it's saved.
</p>

<?php if ($adGroups === 0): ?>
    <div class="alert alert-warning">
        No campaigns yet. <?= Html::a('Build your campaigns', ['/prepare/index']) ?> first, then create ads.
    </div>
<?php elseif (!$summary['hasRun']): ?>
    <div class="alert alert-info">
        <?= $adGroups ?> ad group(s) ready — create their ads.
    </div>
<?php endif; ?>

<?php if ($summary['hasRun']): ?>
    <div class="row g-3 mb-2">
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body text-center">
                <div class="display-6"><?= (int) $summary['generated'] ?></div>
                <div class="text-muted small">ads created<?= $summary['pending'] > 0 ? ' (' . (int) $summary['pending'] . ' pending)' : '' ?></div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body text-center">
                <div class="display-6"><?= (int) $summary['keywordsCovered'] ?></div>
                <div class="text-muted small">keywords covered</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body text-center">
                <div class="display-6"><?= (int) $summary['byStored'] ?> / <?= (int) $summary['byTemplate'] ?></div>
                <div class="text-muted small">curated / template</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card h-100"><div class="card-body text-center">
                <div class="display-6 <?= $summary['invalid'] > 0 ? 'text-danger' : 'text-success' ?>"><?= (int) $summary['invalid'] ?></div>
                <div class="text-muted small">need attention</div>
            </div></div>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($byLanguage as $language => $data): ?>
    <div class="card mt-3">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= Html::encode($data['campaign']) ?></span>
            <?= Html::a(
                Html::encode($data['final_url']),
                $data['final_url'],
                ['target' => '_blank', 'rel' => 'noopener', 'class' => 'small text-decoration-none'],
            ) ?>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <?php foreach ($data['groups'] as $group): ?>
                    <?php /** @var app\models\AdGroup $group */ $ad = $group->generatedAd; ?>
                    <div class="col-lg-6">
                        <div class="border rounded p-3 h-100">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <span class="fw-semibold"><?= Html::encode($group->theme) ?></span>
                                    <span class="badge text-bg-secondary ms-1"><?= (int) $group->keyword_count ?> kw</span>
                                </div>
                                <?php if ($ad !== null): ?>
                                    <?php $isCurated = $ad->generated_by === app\models\GeneratedAd::BY_STORED; ?>
                                    <span class="badge text-bg-<?= $isCurated ? 'primary' : 'light' ?>">
                                        <?= $isCurated ? 'Curated' : 'Template' ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <?php if ($ad === null): ?>
                                <p class="text-muted fst-italic small mb-0">No ad yet.</p>
                            <?php else: ?>
                                <?php if (!$ad->is_valid): ?>
                                    <div class="alert alert-danger py-1 px-2 small"><?= Html::encode((string) $ad->note) ?></div>
                                <?php endif; ?>
                                <div class="text-muted text-uppercase small fw-semibold mt-1">Headlines</div>
                                <ul class="list-unstyled small mb-2">
                                    <?php foreach ($ad->getHeadlines() as $h): ?>
                                        <?= $chip($h, RsaValidator::HEADLINE_MAX) ?>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="text-muted text-uppercase small fw-semibold">Descriptions</div>
                                <ul class="list-unstyled small mb-0">
                                    <?php foreach ($ad->getDescriptions() as $d): ?>
                                        <?= $chip($d, RsaValidator::DESCRIPTION_MAX) ?>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
<?php endforeach; ?>

<?php if ($summary['hasRun']): ?>
    <p class="text-muted small mt-3 mb-0">
        Next: <?= Html::a('export your campaigns', ['/export/index']) ?> for Google Ads.
    </p>
<?php endif; ?>
