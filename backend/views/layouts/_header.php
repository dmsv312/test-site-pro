<?php

declare(strict_types=1);

/** @var yii\web\View $this */

use yii\bootstrap5\Nav;
use yii\bootstrap5\NavBar;
use yii\helpers\Html;

$items = [
    [
        'label' => 'Import & data',
        'url' => ['/import/index'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => 'Keywords',
        'url' => ['/import/keywords'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => 'Cleaning',
        'url' => ['/cleaning/index'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => 'Prepare',
        'url' => ['/prepare/index'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => 'Rules',
        'url' => ['/rules/index'],
        'visible' => !Yii::$app->user->isGuest,
    ],
    [
        'label' => 'Logout (' . Html::encode(Yii::$app->user->identity?->username ?? '') . ')',
        'url' => ['/site/logout'],
        'linkOptions' => [
            'data-method' => 'post',
            'class' => 'nav-link logout',
        ],
        'visible' => !Yii::$app->user->isGuest,
    ],
];

?>
<header id="header">
    <?php NavBar::begin(
        [
            'brandLabel' => Yii::$app->name,
            'brandUrl' => Yii::$app->homeUrl,
            'options' => ['class' => 'navbar-expand-md navbar-dark bg-dark fixed-top']
        ],
    ) ?>
    <?= Nav::widget(
        [
            'options' => ['class' => 'navbar-nav me-auto'],
            'encodeLabels' => false,
            'items' => $items,
        ],
    ) ?>
    <?= Html::button(
        '&#127769;',
        [
            'id' => 'theme-toggle',
            'class' => 'btn btn-link nav-link fs-5',
            'aria-label' => 'Switch to dark mode',
        ],
    ) ?>
    <?php NavBar::end() ?>
</header>
