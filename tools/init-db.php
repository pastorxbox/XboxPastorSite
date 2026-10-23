<?php

declare(strict_types=1);

/**
 * Aplica schema, seed e taxonomia.
 * Uso local: docker compose exec web php /var/www/tools/init-db.php
 */
$configFile = '/var/www/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Configuração em falta.\n");
    exit(1);
}

$config = require $configFile;

require '/var/www/html/includes/db.php';
require '/var/www/html/includes/tags.php';

$pdo = db_connect($config);
$sqlDir = '/var/www/sql';
$files = [
    '001_schema.sql',
    '002_seed.sql',
    '003_taxonomy.sql',
    '004_calendar.sql',
    '005_calendar_seed.sql',
    '006_ads.sql',
    '012_ad_slot_dimensions.sql',
    '013_ad_slot_dimension_defaults.sql',
    '014_push_subscriptions.sql',
    '015_tv_xbox.sql',
    '016_gp_releases_show_on_tv.sql',
    '017_tv_videos_expires_at.sql',
    '018_releases_price_brl.sql',
];

foreach ($files as $file) {
    $path = $sqlDir . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Ficheiro SQL em falta: {$path}\n");
        exit(1);
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Não foi possível ler: {$path}\n");
        exit(1);
    }

    $sql = trim((string) preg_replace('/^\s*SET NAMES[^;]*;/i', '', $sql));
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
        if ($statement === '') {
            continue;
        }
        $pdo->exec($statement);
    }
    echo "Aplicado: {$file}\n";
}

$sectionCol = $pdo->query("SHOW COLUMNS FROM posts LIKE 'section'")->fetch();
if ($sectionCol === false) {
    $pdo->exec("ALTER TABLE posts ADD COLUMN section ENUM('news','article') NOT NULL DEFAULT 'news' AFTER status");
    $pdo->exec('ALTER TABLE posts ADD INDEX idx_section_status_published (section, status, published_at)');
    echo "Coluna posts.section criada.\n";
}

$pdo->exec("UPDATE posts SET section = 'article' WHERE id = 1 AND section = 'news'");

$posts = $pdo->query('SELECT id, title, body FROM posts')->fetchAll();
$linkCount = $pdo->prepare('SELECT COUNT(*) FROM post_tags WHERE post_id = :id');
foreach ($posts as $post) {
    $id = (int) $post['id'];
    $linkCount->execute(['id' => $id]);
    if ((int) $linkCount->fetchColumn() > 0) {
        continue;
    }
    tags_sync_for_post($pdo, $id, tags_infer_from_text((string) $post['title'], (string) $post['body']));
}

echo "Base de dados pronta.\n";
