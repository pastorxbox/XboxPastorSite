<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set("display_errors", "1");
$config = require dirname(__DIR__) . "/config/config.php";
require_once __DIR__ . "/includes/db.php";
require_once __DIR__ . "/includes/helpers.php";
require_once __DIR__ . "/includes/i18n.php";
require_once __DIR__ . "/includes/calendar.php";
require_once __DIR__ . "/includes/tvxbox.php";
$pdo = db_connect($config);
$items = tvxbox_list_for_player($pdo);
echo "COUNT=" . count($items) . "\n";
foreach ($items as $it) {
    if (($it["youtubeId"] ?? "") === "WhQm-ExRz60" || (is_array($it["game"] ?? null) && str_contains((string)($it["game"]["title"] ?? ""), "CONTROL"))) {
        echo json_encode($it, JSON_UNESCAPED_UNICODE) . "\n";
    }
}
$withGame = 0;
$without = 0;
foreach ($items as $it) {
    if (!empty($it["game"]["id"])) { $withGame++; } else { $without++; }
}
echo "withGame=$withGame withoutGame=$without\n";
echo "php_ok\n";
