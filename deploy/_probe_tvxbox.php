<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$config = require dirname(__DIR__) . '/config/config.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/i18n.php';
require_once __DIR__ . '/includes/calendar.php';
require_once __DIR__ . '/includes/tvxbox.php';

$u = 'https://youtu.be/3OG4XeEcYjg?is=FDt-2puSI2WRml5u';
echo 'parse=' . var_export(tvxbox_parse_youtube_id($u), true) . PHP_EOL;

$pdo = db_connect($config);
$rows = $pdo->query(
    "SELECT id, title, trailer_url, show_on_tv FROM gp_releases WHERE id IN (22,23)"
)->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $row) {
    $id = tvxbox_parse_youtube_id((string) $row['trailer_url']);
    echo 'gp#' . $row['id'] . ' parse=' . var_export($id, true) . PHP_EOL;
}

$list = tvxbox_list_for_player($pdo);
echo 'count=' . count($list) . PHP_EOL;
foreach ($list as $v) {
    $y = (string) ($v['youtubeId'] ?? '');
    $id = (string) ($v['id'] ?? '');
    if (in_array($y, ['3OG4XeEcYjg', 'Iz9dEXYRgO8'], true) || str_starts_with($id, 'gp-')) {
        echo json_encode($v, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
echo 'hasTCG=' . (in_array('3OG4XeEcYjg', array_column($list, 'youtubeId'), true) ? 'yes' : 'no') . PHP_EOL;
echo 'ids=' . implode(',', array_map('strval', array_column($list, 'id'))) . PHP_EOL;
