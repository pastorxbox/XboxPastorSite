<?php
$config = require dirname(__DIR__) . "/config/config.php";
$pdo = new PDO(
  sprintf("mysql:host=%s;dbname=%s;charset=utf8mb4", $config["db"]["host"], $config["db"]["name"]),
  $config["db"]["user"],
  $config["db"]["pass"],
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
$pdo->exec("UPDATE tv_videos v JOIN gp_releases r ON r.id = v.gp_release_id SET v.youtube_url = r.trailer_url, v.youtube_id = 'WhQm-ExRz60' WHERE v.id = 35 AND r.id = 17");
echo "updated35=" . $pdo->query("SELECT youtube_id FROM tv_videos WHERE id=35")->fetchColumn() . "\n";
$del = $pdo->exec("DELETE FROM tv_videos WHERE id = 6 AND youtube_id = 'WhQm-ExRz60' AND gp_release_id IS NULL");
echo "deleted6=$del\n";
