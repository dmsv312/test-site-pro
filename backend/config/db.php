<?php

// Подключение к БД. Значения берём из окружения (Docker), с локальными дефолтами.
$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '5432';
$name = getenv('DB_DATABASE') ?: 'sitepro';
$user = getenv('DB_USERNAME') ?: 'sitepro';
$pass = getenv('DB_PASSWORD');
if ($pass === false) {
    $pass = 'secret';
}

return [
    'class' => \yii\db\Connection::class,
    'dsn' => "pgsql:host={$host};port={$port};dbname={$name}",
    'username' => $user,
    'password' => $pass,
    'charset' => 'utf8',

    // Кэш схемы в проде.
    'enableSchemaCache' => !YII_DEBUG,
    'schemaCacheDuration' => 3600,
    'schemaCache' => 'cache',
];
