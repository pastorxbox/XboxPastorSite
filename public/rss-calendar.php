<?php

declare(strict_types=1);

$configFile = dirname(__DIR__, 1) . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = '/var/www/config/config.php';
}
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Configuração em falta.';
    exit;
}

$config = require $configFile;

require __DIR__ . '/includes/db.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/calendar.php';
require __DIR__ . '/includes/feed.php';

try {
    $pdo = db_connect($config);
    $items = calendar_list_for_feed($pdo, $config);
} catch (Throwable) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erro ao gerar feed RSS do calendário.';
    exit;
}

header('Content-Type: application/rss+xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=300');

echo feed_render_calendar_rss($config, $items);
