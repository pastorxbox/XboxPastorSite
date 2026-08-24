<?php

declare(strict_types=1);

ini_set('display_errors', '0');

$configFile = dirname(__DIR__, 2) . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = '/var/www/config/config.php';
}
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Configuração em falta.']);
    exit;
}

$config = require $configFile;

$autoload = '/var/www/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Dependências em falta.']);
    exit;
}

require $autoload;
require dirname(__DIR__) . '/includes/db.php';
require dirname(__DIR__) . '/includes/posts.php';
require dirname(__DIR__) . '/includes/helpers.php';
require dirname(__DIR__) . '/includes/api-common.php';
require dirname(__DIR__) . '/includes/api-posts.php';
require dirname(__DIR__) . '/includes/api-media.php';

try {
    $pdo = db_connect($config);
} catch (Throwable) {
    api_error(500, 'Database unavailable');
}

$path = api_route_path();

if ($path === '/api' || $path === '/api/index.php') {
    api_handle_root();
}

if ($path === '/api/posts') {
    api_handle_posts_create($pdo, $config);
}

if (preg_match('#^/api/posts/(\d+)$#', $path, $matches) === 1) {
    api_handle_posts_update($pdo, $config, (int) $matches[1]);
}

if ($path === '/api/media') {
    api_handle_media_upload($config);
}

api_error(404, 'Not found');
