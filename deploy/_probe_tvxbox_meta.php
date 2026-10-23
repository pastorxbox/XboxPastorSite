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

$pdo = db_connect($config);
$items = tvxbox_list_for_player($pdo);
echo 'COUNT=' . count($items) . PHP_EOL;
$empty = 0;
foreach ($items as $i => $it) {
    $tag = (string) ($it['tag'] ?? '');
    $cmt = (string) ($it['comment'] ?? '');
    $rw = is_array($it['rewards'] ?? null) ? (string) ($it['rewards']['label'] ?? '-') : '-';
    $flag = ($tag === '' && $cmt === '') ? 'EMPTY_META' : 'ok';
    if ($flag === 'EMPTY_META') {
        $empty++;
    }
    echo '#' . $i
        . ' id=' . (string) $it['id']
        . ' yt=' . $it['youtubeId']
        . ' gp=' . (!empty($it['onGamePass']) ? '1' : '0')
        . ' tag=[' . $tag . ']'
        . ' cmt=[' . substr($cmt, 0, 70) . ']'
        . ' rewards=' . $rw
        . ' ' . $flag
        . PHP_EOL;
}
echo "EMPTY_COUNT={$empty}\n";

echo "--- gp CONTROL ---\n";
$stmt = $pdo->query(
    "SELECT id, title, show_on_tv, on_gamepass, price_brl, release_at, LEFT(trailer_url,120) AS traile
     FROM gp_releases
     WHERE title LIKE '%CONTROL%' OR title LIKE '%Control%' OR title LIKE '%Resonan%'
     ORDER BY id DESC LIMIT 20"
);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

echo "--- tv empty tag/comment published on tv ---\n";
$stmt = $pdo->query(
    "SELECT v.id, v.youtube_id, v.tag, v.comment, v.show_on_tv, v.gp_release_id, v.expires_at,
            r.title, r.release_at, r.on_gamepass, r.price_brl, r.show_on_tv AS r_tv
     FROM tv_videos v
     LEFT JOIN gp_releases r ON r.id = v.gp_release_id
     WHERE v.status='published' AND v.show_on_tv=1
       AND (v.expires_at IS NULL OR v.expires_at >= CURDATE())
     ORDER BY v.id ASC"
);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $metaEmpty = trim((string) ($r['tag'] ?? '')) === '' && trim((string) ($r['comment'] ?? '')) === '';
    $noReleaseAt = empty($r['release_at']);
    if ($metaEmpty || $noReleaseAt || empty($r['gp_release_id'])) {
        echo json_encode($r, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
