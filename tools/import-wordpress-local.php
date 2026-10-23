#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Importa posts do WordPress a partir de uma cópia local (backup) + MySQL WP.
 * Não depende da REST API pública.
 *
 * Uso na Hostinger:
 *   php domains/pastorxbox.com.br/tools/import-wordpress-local.php \
 *     /home/u285071835/backups/public_html-old-YYYY-MM-DD-HHMMSS
 */

$domainRoot = dirname(__DIR__);
$configFile = $domainRoot . '/config/config.php';
$htmlRoot = $domainRoot . '/public_html';

if (!is_file($configFile)) {
    fwrite(STDERR, "Config em falta: {$configFile}\n");
    exit(1);
}

$wpRoot = $argv[1] ?? '';
if ($wpRoot === '' || !is_dir($wpRoot) || !is_file(rtrim($wpRoot, '/') . '/wp-load.php')) {
    fwrite(STDERR, "Indique o caminho do backup WP (pasta com wp-load.php).\n");
    fwrite(STDERR, "Ex.: php import-wordpress-local.php ~/backups/public_html-old-...\n");
    exit(1);
}

$wpRoot = rtrim($wpRoot, '/');
$config = require $configFile;
require $htmlRoot . '/includes/db.php';
require $htmlRoot . '/includes/tags.php';
require __DIR__ . '/lib/wp-import.php';

$uploadsRoot = $htmlRoot . '/uploads';
if (!is_dir($uploadsRoot) && !mkdir($uploadsRoot, 0755, true) && !is_dir($uploadsRoot)) {
    wp_import_fail("Não foi possível criar uploads: {$uploadsRoot}");
}

// Garante que os ficheiros do WP estão em /uploads (já copiados no deploy; reforço aqui)
$wpUploads = $wpRoot . '/wp-content/uploads';
if (is_dir($wpUploads)) {
    wp_import_log('A sincronizar uploads WP → public_html/uploads ...');
    // rsync-like recursive copy without overwriting newer files is heavy; use cp -a via shell for speed
    $cmd = 'cp -an ' . escapeshellarg($wpUploads . '/.') . ' ' . escapeshellarg($uploadsRoot . '/');
    passthru($cmd, $copyCode);
    if ($copyCode !== 0) {
        wp_import_log("Aviso: cópia de uploads terminou com código {$copyCode}");
    }
}

// Bootstrap WordPress (só leitura de posts)
define('WP_USE_THEMES', false);
// Evitar redirects / manutenções
if (!defined('SHORTINIT')) {
    // SHORTINIT too limited for the_content; load full WP
}
require $wpRoot . '/wp-load.php';

$pdo = db_connect($config);
$siteHost = 'pastorxbox.com.br';

$query = new WP_Query([
    'post_type' => 'post',
    'post_status' => 'publish',
    'posts_per_page' => -1,
    'orderby' => 'ID',
    'order' => 'ASC',
    'no_found_rows' => true,
]);

$total = (int) $query->post_count;
wp_import_log("WordPress local: {$total} posts publicados.");

$imported = 0;
foreach ($query->posts as $wpPost) {
    $id = (int) $wpPost->ID;
    wp_import_log("A importar post {$id}: " . get_the_title($wpPost));

    $categories = wp_get_post_categories($id);
    $content = apply_filters('the_content', $wpPost->post_content);

    $featured = null;
    $thumbId = (int) get_post_thumbnail_id($id);
    if ($thumbId > 0) {
        $src = wp_get_attachment_image_src($thumbId, 'full');
        if (is_array($src) && !empty($src[0])) {
            $featured = [
                'source_url' => (string) $src[0],
                'alt' => (string) get_post_meta($thumbId, '_wp_attachment_image_alt', true),
            ];
        }
    }

    // Formato compatível com wp_import_upsert_post (como a REST API)
    $post = [
        'id' => $id,
        'date' => $wpPost->post_date,
        'title' => ['rendered' => get_the_title($wpPost)],
        'content' => ['rendered' => $content],
        'categories' => $categories,
        '_embedded' => [
            'wp:featuredmedia' => $featured !== null ? [[
                'source_url' => $featured['source_url'],
                'alt_text' => $featured['alt'],
            ]] : [],
        ],
    ];

    // Reescreve URLs /wp-content/uploads/ → ficheiros locais sem HTTP
    // (wp_import_download_upload já usa ficheiro local se existir)
    wp_import_upsert_post($pdo, $post, $uploadsRoot, $siteHost);
    ++$imported;
}

wp_import_fix_auto_increment($pdo);
wp_import_log("Importação concluída: {$imported} posts processados.");
