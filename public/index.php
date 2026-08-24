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
require __DIR__ . '/includes/posts.php';
require __DIR__ . '/includes/helpers.php';
require __DIR__ . '/includes/content.php';
require __DIR__ . '/includes/vitrine-layout.php';

try {
    $pdo = db_connect($config);
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erro ao ligar à base de dados.';
    exit;
}

$postId = null;
if (isset($_GET['p']) && $_GET['p'] !== '') {
    $postId = (int) $_GET['p'];
}

$page = 1;
if (isset($_GET['paged']) && $_GET['paged'] !== '') {
    $page = max(1, (int) $_GET['paged']);
}

if ($postId !== null && $postId > 0) {
    $post = posts_get_published_by_id($pdo, $postId);
    if ($post === null) {
        render_vitrine_page(
            $config,
            'Não encontrado',
            render_not_found_content(),
            [],
            404
        );
        exit;
    }

    $url = site_post_url($config, $postId);
    $description = excerpt_from_html((string) $post['body']);

    render_vitrine_page(
        $config,
        (string) $post['title'],
        render_article_content($post),
        [
            'canonical' => $url,
            'og_title' => (string) $post['title'],
            'og_description' => $description,
            'og_type' => 'article',
            'og_url' => $url,
        ]
    );
    exit;
}

$totalPosts = posts_count_published($pdo);
$totalPages = max(1, (int) ceil($totalPosts / POSTS_PER_PAGE));
if ($page > $totalPages && $totalPosts > 0) {
    render_vitrine_page(
        $config,
        'Não encontrado',
        render_not_found_content(),
        [],
        404
    );
    exit;
}

$posts = posts_list_published($pdo, $page);
$homeMeta = [
    'canonical' => site_home_url($config, $page),
    'og_title' => $page > 1 ? 'Página ' . $page : 'Início',
    'og_description' => 'Notícias e análises sobre Xbox.',
    'og_type' => 'website',
    'og_url' => site_home_url($config, $page),
];

render_vitrine_page(
    $config,
    $page > 1 ? 'Página ' . $page : 'Início',
    render_home_content($config, $posts, $page, $totalPages),
    $homeMeta
);
