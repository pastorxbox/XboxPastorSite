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

$autoload = dirname(__DIR__, 2) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    $autoload = '/var/www/vendor/autoload.php';
}
if (!is_file($autoload)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Dependências em falta.']);
    exit;
}

require $autoload;
require dirname(__DIR__) . '/includes/db.php';
require dirname(__DIR__) . '/includes/i18n.php';
require dirname(__DIR__) . '/includes/posts.php';
require dirname(__DIR__) . '/includes/helpers.php';
require dirname(__DIR__) . '/includes/tags.php';
require dirname(__DIR__) . '/includes/calendar.php';
require dirname(__DIR__) . '/includes/api-common.php';
require dirname(__DIR__) . '/includes/api-posts.php';
require dirname(__DIR__) . '/includes/api-media.php';
require dirname(__DIR__) . '/includes/api-brand.php';
require dirname(__DIR__) . '/includes/api-home-today.php';
require dirname(__DIR__) . '/includes/api-calendar.php';
require dirname(__DIR__) . '/includes/ads.php';
require dirname(__DIR__) . '/includes/api-ads.php';
require dirname(__DIR__) . '/includes/tvxbox.php';
require dirname(__DIR__) . '/includes/api-tvxbox.php';
require dirname(__DIR__) . '/includes/push.php';
require dirname(__DIR__) . '/includes/api-push.php';

try {
    $pdo = db_connect($config);
} catch (Throwable) {
    api_error(500, 'Database unavailable');
}

$path = api_route_path();

if ($path === '/api' || $path === '/api/index.php') {
    api_handle_root();
}

if ($path === '/api/push/vapid-public-key') {
    api_handle_push_vapid_public($config);
}

if ($path === '/api/push/subscribe') {
    api_handle_push_subscribe($pdo, $config);
}

if ($path === '/api/posts') {
    api_handle_posts_collection($pdo, $config);
}

if (preg_match('#^/api/posts/(\d+)$#', $path, $matches) === 1) {
    api_handle_posts_item($pdo, $config, (int) $matches[1]);
}

if (preg_match('#^/api/posts/(\d+)/translations/(en|es)$#', $path, $matches) === 1) {
    api_handle_post_translation_item($pdo, $config, (int) $matches[1], (string) $matches[2]);
}

if ($path === '/api/settings/i18n') {
    api_handle_i18n_settings($pdo, $config);
}

if ($path === '/api/settings/brand') {
    api_handle_brand_settings($pdo, $config);
}

if ($path === '/api/settings/home-today') {
    api_handle_home_today_settings($pdo, $config);
}

if ($path === '/api/media') {
    api_handle_media_upload($config);
}

if (preg_match('#^/api/calendar/(gamepass|events|history)/(\d+)/translations/(en|es)$#', $path, $matches) === 1) {
    $entityType = $matches[1] === 'events' ? 'event' : (string) $matches[1];
    api_handle_calendar_translation_item($pdo, $config, $entityType, (int) $matches[2], (string) $matches[3]);
}

if ($path === '/api/calendar/gamepass') {
    api_handle_gamepass_collection($pdo, $config);
}

if (preg_match('#^/api/calendar/gamepass/(\d+)$#', $path, $matches) === 1) {
    api_handle_gamepass_item($pdo, $config, (int) $matches[1]);
}

if ($path === '/api/calendar/events') {
    api_handle_event_collection($pdo, $config);
}

if (preg_match('#^/api/calendar/events/(\d+)$#', $path, $matches) === 1) {
    api_handle_event_item($pdo, $config, (int) $matches[1]);
}

if ($path === '/api/calendar/history') {
    api_handle_history_collection($pdo, $config);
}

if (preg_match('#^/api/calendar/history/(\d+)$#', $path, $matches) === 1) {
    api_handle_history_item($pdo, $config, (int) $matches[1]);
}

if ($path === '/api/ads') {
    api_handle_ads_list($pdo, $config);
}

if (preg_match('#^/api/ads/slots/([a-z0-9_]+)$#', $path, $matches) === 1) {
    api_handle_ads_slot_patch($pdo, $config, (string) $matches[1]);
}

if (preg_match('#^/api/ads/slots/([a-z0-9_]+)/slides$#', $path, $matches) === 1) {
    api_handle_ads_slide_create($pdo, $config, (string) $matches[1]);
}

if (preg_match('#^/api/ads/slides/(\d+)$#', $path, $matches) === 1) {
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'DELETE') {
        api_handle_ads_slide_delete($pdo, $config, (int) $matches[1]);
    }
    api_handle_ads_slide_update($pdo, $config, (int) $matches[1]);
}

if ($path === '/api/tvxbox') {
    api_handle_tvxbox_collection($pdo, $config);
}

if ($path === '/api/tvxbox/link') {
    api_handle_tvxbox_link($pdo, $config);
}

if (preg_match('#^/api/tvxbox/(\d+)/translations/(en|es)$#', $path, $matches) === 1) {
    api_handle_tvxbox_translation_item($pdo, $config, (int) $matches[1], (string) $matches[2]);
}

if (preg_match('#^/api/tvxbox/(\d+)$#', $path, $matches) === 1) {
    api_handle_tvxbox_item($pdo, $config, (int) $matches[1]);
}

api_error(404, 'Not found');
