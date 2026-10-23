<?php

declare(strict_types=1);

/**
 * Importa posts publicados do WordPress via REST (Fase 4).
 * Uso local:
 *   docker compose exec web php /var/www/tools/import-wordpress.php
 *   docker compose exec web php /var/www/tools/import-wordpress.php 1216
 */
$configFile = '/var/www/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Configuração em falta.\n");
    exit(1);
}

$config = require $configFile;
require '/var/www/html/includes/db.php';
require '/var/www/html/includes/tags.php';
require __DIR__ . '/lib/wp-import.php';

$restBaseUrl = rtrim((string) ($config['wp_rest_base_url'] ?? 'https://pastorxbox.com.br/wp-json/wp/v2'), '/');
$uploadsRoot = '/var/www/html/uploads';
$siteHost = wp_import_site_host($restBaseUrl);

if (!is_dir($uploadsRoot) && !mkdir($uploadsRoot, 0755, true) && !is_dir($uploadsRoot)) {
    wp_import_fail("Não foi possível criar uploads: {$uploadsRoot}");
}

$pdo = db_connect($config);

$ids = [];
foreach (array_slice($argv, 1) as $arg) {
    if (ctype_digit((string) $arg) && (int) $arg > 0) {
        $ids[] = (int) $arg;
    }
}

$imported = 0;
if ($ids !== []) {
    foreach ($ids as $id) {
        wp_import_log("A importar post {$id}...");
        $post = wp_import_fetch_post_by_id($restBaseUrl, $id);
        wp_import_upsert_post($pdo, $post, $uploadsRoot, $siteHost);
        ++$imported;
        wp_import_log('Importado: ' . wp_import_normalize_title((string) ($post['title']['rendered'] ?? '')));
    }
} else {
    $meta = wp_import_fetch_meta($restBaseUrl, 100);
    wp_import_log("WordPress: {$meta['total']} posts em {$meta['total_pages']} página(s).");

    for ($page = 1; $page <= $meta['total_pages']; ++$page) {
        wp_import_log("A importar página {$page}/{$meta['total_pages']}...");
        $posts = wp_import_fetch_posts_page($restBaseUrl, $page, $meta['per_page']);
        foreach ($posts as $post) {
            wp_import_upsert_post($pdo, $post, $uploadsRoot, $siteHost);
            ++$imported;
        }
    }
}

wp_import_fix_auto_increment($pdo);

wp_import_log("Importação concluída: {$imported} posts processados.");
wp_import_log('AUTO_INCREMENT ajustado para MAX(id)+1.');
