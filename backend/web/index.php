<?php

declare(strict_types=1);

// Режим берём из окружения (Docker); по умолчанию — прод.
$yiiDebug = getenv('YII_DEBUG');
defined('YII_DEBUG') or define('YII_DEBUG', $yiiDebug === 'true' || $yiiDebug === '1');
defined('YII_ENV') or define('YII_ENV', getenv('YII_ENV') ?: 'prod');

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../vendor/yiisoft/yii2/Yii.php';

$config = require __DIR__ . '/../config/web.php';

(new yii\web\Application($config))->run();
