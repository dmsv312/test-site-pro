<?php

$params = require __DIR__ . '/params.php';
$db = require __DIR__ . '/db.php';

// Cookie validation key comes from the environment. In production it is mandatory —
// we never ship a shared committed key (a bad idea and a secret in git). Locally a
// clearly-labeled placeholder is used so a fresh clone runs without configuration.
$cookieValidationKey = getenv('COOKIE_VALIDATION_KEY') ?: '';
if ($cookieValidationKey === '') {
    if (YII_ENV_PROD) {
        throw new \yii\base\InvalidConfigException(
            'COOKIE_VALIDATION_KEY must be set in production (see .env.example).'
        );
    }
    $cookieValidationKey = 'dev-local-insecure-key-not-for-production';
}

// The public site is served only over HTTPS (Cloudflare edge; the origin listens on
// localhost). Mark auth-bearing cookies Secure in that case so they never travel over
// plain HTTP; local http://127.0.0.1 dev keeps them non-Secure so login still works.
$secureCookies = str_starts_with((string) getenv('APP_URL'), 'https://');
$cookieDefaults = [
    'httpOnly' => true,
    'secure' => $secureCookies,
    'sameSite' => \yii\web\Cookie::SAME_SITE_LAX,
];

$config = [
    'id' => 'basic',
    'name' => 'Site.pro Keyword Manager',
    'basePath' => dirname(__DIR__),
    // The app is a login-gated admin tool; the home route is the import dashboard.
    'defaultRoute' => 'import/index',
    'bootstrap' => ['log'],
    'aliases' => [
        '@bower' => '@vendor/bower-asset',
        '@npm'   => '@vendor/npm-asset',
    ],
    'components' => [
        'request' => [
            'cookieValidationKey' => $cookieValidationKey,
            'csrfCookie' => $cookieDefaults,
        ],
        'session' => [
            'cookieParams' => [
                'httponly' => true,
                'secure' => $secureCookies,
                'samesite' => \yii\web\Cookie::SAME_SITE_LAX,
            ],
        ],
        'cache' => [
            'class' => \yii\caching\FileCache::class,
        ],
        'user' => [
            'identityClass' => \app\models\User::class,
            'enableAutoLogin' => true,
            // Remember-me cookie carries an auth token — same Secure/HttpOnly hardening.
            'identityCookie' => ['name' => '_identity'] + $cookieDefaults,
        ],
        'errorHandler' => [
            'errorAction' => 'site/error',
        ],
        'log' => [
            'traceLevel' => YII_DEBUG ? 3 : 0,
            'targets' => [
                [
                    'class' => \yii\log\FileTarget::class,
                    'levels' => ['error', 'warning'],
                ],
            ],
        ],
        'db' => $db,
        'urlManager' => [
            'enablePrettyUrl' => true,
            'showScriptName' => false,
            'rules' => [
            ],
        ],
    ],
    'params' => $params,
];

if (YII_ENV_DEV) {
    // configuration adjustments for 'dev' environment
    $config['bootstrap'][] = 'debug';
    $config['modules']['debug'] = [
        'class' => \yii\debug\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];

    $config['bootstrap'][] = 'gii';
    $config['modules']['gii'] = [
        'class' => \yii\gii\Module::class,
        // uncomment the following to add your IP if you are not connecting from localhost.
        //'allowedIPs' => ['127.0.0.1', '::1'],
    ];
}

return $config;
