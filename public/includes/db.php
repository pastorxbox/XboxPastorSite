<?php

declare(strict_types=1);

function db_connect(array $config): PDO
{
    $db = $config['db'] ?? [];
    $host = (string) ($db['host'] ?? 'mysql');
    $name = (string) ($db['name'] ?? 'pastorxbox');
    $user = (string) ($db['user'] ?? 'pastorxbox');
    $pass = (string) ($db['pass'] ?? '');
    $charset = (string) ($db['charset'] ?? 'utf8mb4');

    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $host, $name, $charset);

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci',
    ]);
}
