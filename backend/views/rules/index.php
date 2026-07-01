<?php

declare(strict_types=1);

use app\models\TermListRecord;
use yii\bootstrap5\ActiveForm;
use yii\helpers\Html;

/** @var yii\web\View $this */
/** @var app\models\RuleConfig[] $thresholds */
/** @var app\models\BrandTerm[] $brandTerms */
/** @var app\models\ForbiddenTerm[] $forbiddenTerms */
/** @var app\models\BrandTerm $newBrand */
/** @var app\models\ForbiddenTerm $newForbidden */

$this->title = 'Cleaning rules';
$this->params['breadcrumbs'][] = $this->title;

/** Render one editable term list (brand / forbidden) as a card. */
$termList = function (string $list, string $title, string $help, array $terms, TermListRecord $newTerm) {
    ob_start();
    ?>
    <div class="card h-100">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><?= Html::encode($title) ?></span>
            <span class="badge text-bg-secondary"><?= count($terms) ?></span>
        </div>
        <div class="card-body">
            <p class="text-muted small"><?= Html::encode($help) ?></p>

            <?php if ($terms === []): ?>
                <p class="text-muted fst-italic">No terms yet.</p>
            <?php else: ?>
                <ul class="list-group mb-3">
                    <?php foreach ($terms as $t): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center py-1">
                            <span><?= Html::encode($t->term) ?>
                                <?php if ($t->note): ?>
                                    <small class="text-muted">— <?= Html::encode($t->note) ?></small>
                                <?php endif; ?>
                            </span>
                            <?= Html::a('remove', ['delete-term', 'list' => $list, 'id' => $t->id], [
                                'class' => 'btn btn-sm btn-outline-danger py-0',
                                'data' => ['method' => 'post', 'confirm' => "Remove “{$t->term}”?"],
                            ]) ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <?php $form = ActiveForm::begin(['action' => ['add-term', 'list' => $list]]); ?>
                <div class="input-group">
                    <?= Html::activeTextInput($newTerm, 'term', [
                        'class' => 'form-control',
                        'placeholder' => 'add a term…',
                        'aria-label' => 'term',
                    ]) ?>
                    <?= Html::submitButton('Add', ['class' => 'btn btn-outline-primary']) ?>
                </div>
            <?php ActiveForm::end(); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
};
?>
<h1><?= Html::encode($this->title) ?></h1>
<p class="text-muted">
    The thresholds and lists below drive the cleaning pipeline. Editing them here changes how the
    next cleaning run scores keywords — no deploy needed. Re-run cleaning after a change to apply it.
</p>

<div class="card mb-4">
    <div class="card-header">Thresholds</div>
    <div class="card-body">
        <?php $form = ActiveForm::begin(['action' => ['save-config']]); ?>
            <div class="row g-3 align-items-end">
                <?php foreach ($thresholds as $c): ?>
                    <div class="col-md-4">
                        <label class="form-label" for="rule-<?= Html::encode($c->name) ?>">
                            <?= Html::encode($c->label ?: $c->name) ?>
                        </label>
                        <?= Html::input('number', "RuleConfig[value][{$c->name}]", $c->value, [
                            'id' => 'rule-' . $c->name,
                            'class' => 'form-control',
                            'min' => 0,
                            'step' => 1,
                        ]) ?>
                    </div>
                <?php endforeach; ?>
                <div class="col-md-4">
                    <?= Html::submitButton('Save thresholds', ['class' => 'btn btn-primary']) ?>
                </div>
            </div>
        <?php ActiveForm::end(); ?>
    </div>
</div>

<div class="row g-4">
    <div class="col-lg-6">
        <?= $termList(
            'brand',
            'Brand terms',
            'Keywords whose normalized term contains any of these are dropped as brand names '
                . '(site.pro’s own brand plus competitor brands).',
            $brandTerms,
            $newBrand,
        ) ?>
    </div>
    <div class="col-lg-6">
        <?= $termList(
            'forbidden',
            'Forbidden terms',
            'Terms never allowed into a campaign. Applied during preparation (stage 5).',
            $forbiddenTerms,
            $newForbidden,
        ) ?>
    </div>
</div>
