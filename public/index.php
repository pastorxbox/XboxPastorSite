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
require_once __DIR__ . '/includes/i18n.php';
require __DIR__ . '/includes/tags.php';
require __DIR__ . '/includes/content.php';
require_once __DIR__ . '/includes/home-today.php';
require_once __DIR__ . '/includes/calendar.php';
require __DIR__ . '/includes/ads.php';
require __DIR__ . '/includes/vitrine-layout.php';

try {
    $pdo = db_connect($config);
    $GLOBALS['pdo'] = $pdo;
} catch (Throwable $e) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Erro ao ligar à base de dados.';
    exit;
}

ads_bootstrap($pdo);

$postId = null;
if (isset($_GET['p']) && $_GET['p'] !== '') {
    $postId = (int) $_GET['p'];
}

$page = 1;
if (isset($_GET['paged']) && $_GET['paged'] !== '') {
    $page = max(1, (int) $_GET['paged']);
}

$secao = isset($_GET['secao']) ? strtolower(trim((string) $_GET['secao'])) : '';
$tagSlug = isset($_GET['tag']) ? strtolower(trim((string) $_GET['tag'])) : '';
$popularTags = tags_list_popular($pdo, 16);

if ($postId !== null && $postId > 0) {
    $post = posts_get_published_by_id($pdo, $postId);
    $recentPosts = posts_list_recent($pdo, 5, $postId);
    if ($post === null) {
        render_vitrine_page(
            $config,
            t('ui.not_found'),
            render_not_found_content(),
            ['nav' => 'none'],
            404,
            $recentPosts,
            $popularTags
        );
        exit;
    }

    $tagged = posts_attach_tags($pdo, [$post]);
    $post = $tagged[0];
    $url = site_post_url($config, $postId);
    $description = excerpt_from_html((string) $post['body']);
    $nav = section_public_slug((string) ($post['section'] ?? 'news'));
    $featuredImage = extract_featured_image((string) $post['body']);
    $ogImage = absolute_url(
        $config,
        (string) ($featuredImage['src'] ?? default_share_image_path())
    );

    render_vitrine_page(
        $config,
        (string) $post['title'],
        render_article_content($post, $config),
        [
            'canonical' => $url,
            'og_title' => (string) $post['title'],
            'og_description' => $description,
            'og_type' => 'article',
            'og_url' => $url,
            'og_image' => $ogImage,
            'nav' => $nav,
        ],
        200,
        $recentPosts,
        $popularTags
    );
    exit;
}

$recentPosts = posts_list_recent($pdo, 5);

if ($secao === 'busca') {
    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : '';
    $searchUrl = site_search_url($config, $q, $page);
    $tooShort = $q !== '' && mb_strlen($q) < 2;
    $totalPosts = ($q !== '' && !$tooShort) ? posts_count_search($pdo, $q) : 0;
    $totalPages = max(1, (int) ceil($totalPosts / POSTS_PER_PAGE));
    if ($page > $totalPages && $totalPosts > 0) {
        render_vitrine_page(
            $config,
            t('ui.not_found'),
            render_not_found_content(),
            ['nav' => 'none'],
            404,
            $recentPosts,
            $popularTags
        );
        exit;
    }

    $posts = ($q !== '' && !$tooShort)
        ? posts_attach_tags($pdo, posts_search_published($pdo, $q, $page))
        : [];
    $calendarHits = ($q !== '' && !$tooShort && $page === 1)
        ? calendar_search($pdo, $q, 8)
        : [];

    $heading = $q !== '' ? 'Resultados para “' . $q . '”' : 'Busca';
    $description = $q !== ''
        ? ($totalPosts . ' publicação(ões) encontrada(s).')
        : 'Pesquise notícias, artigos e o calendário Xbox.';

    render_vitrine_page(
        $config,
        $heading,
        render_search_content($config, $q, $posts, $page, $totalPages, $calendarHits, $tooShort),
        [
            'canonical' => $searchUrl,
            'og_title' => $heading,
            'og_description' => $description,
            'og_type' => 'website',
            'og_url' => $searchUrl,
            'nav' => 'none',
        ],
        200,
        $recentPosts,
        $popularTags
    );
    exit;
}

if ($secao === 'calendario') {
    $calendarUrl = rtrim((string) ($config['site_base_url'] ?? ''), '/') . '/calendario';
    $calendarEvents = calendar_list_upcoming_events($pdo);
    $calendarTitle = i18n_t($pdo, 'site.calendar');
    render_vitrine_page(
        $config,
        $calendarTitle . ' Xbox',
        render_calendar_content(
            $config,
            calendar_list_upcoming_gamepass($pdo),
            calendar_list_history_month($pdo),
            $calendarEvents
        ),
        [
            'canonical' => $calendarUrl,
            'og_title' => $calendarTitle . ' Xbox',
            'og_description' => t('cal.og'),
            'og_type' => 'website',
            'og_url' => $calendarUrl,
            'nav' => 'calendario',
        ],
        200,
        $recentPosts,
        $popularTags,
        $calendarEvents
    );
    exit;
}

if ($tagSlug !== '') {
    $tag = tags_get_by_slug($pdo, $tagSlug);
    if ($tag === null) {
        render_vitrine_page(
            $config,
            t('ui.not_found'),
            render_not_found_content(),
            ['nav' => 'none'],
            404,
            $recentPosts,
            $popularTags
        );
        exit;
    }

    $totalPosts = posts_count_published_with_tag($pdo, $tagSlug);
    $totalPages = max(1, (int) ceil($totalPosts / POSTS_PER_PAGE));
    if ($page > $totalPages && $totalPosts > 0) {
        render_vitrine_page(
            $config,
            t('ui.not_found'),
            render_not_found_content(),
            ['nav' => 'none'],
            404,
            $recentPosts,
            $popularTags
        );
        exit;
    }

    $posts = posts_attach_tags($pdo, posts_list_published_with_tag($pdo, $tagSlug, $page));
    $tagName = (string) $tag['name'];
    render_vitrine_page(
        $config,
        '#' . $tagName,
        render_home_content(
            $config,
            $posts,
            $page,
            $totalPages,
            '#' . $tagName,
            'Notícias e artigos com esta hashtag — a mesma das redes sociais.',
            null,
            $tagSlug
        ),
        [
            'canonical' => site_tag_url($config, $tagSlug, $page),
            'og_title' => '#' . $tagName,
            'og_description' => 'Publicações com #' . $tagName,
            'og_type' => 'website',
            'og_url' => site_tag_url($config, $tagSlug, $page),
            'nav' => 'none',
        ],
        200,
        $recentPosts,
        $popularTags
    );
    exit;
}

if ($secao === 'noticias' || $secao === 'artigos') {
    $section = section_normalize($secao);
    $totalPosts = posts_count_published_in_section($pdo, $section);
    $totalPages = max(1, (int) ceil($totalPosts / POSTS_PER_PAGE));
    if ($page > $totalPages && $totalPosts > 0) {
        render_vitrine_page(
            $config,
            t('ui.not_found'),
            render_not_found_content(),
            ['nav' => $secao],
            404,
            $recentPosts,
            $popularTags
        );
        exit;
    }

    $posts = posts_attach_tags($pdo, posts_list_published_in_section($pdo, $section, $page));
    $heading = section_label($section);
    render_vitrine_page(
        $config,
        $heading,
        render_home_content(
            $config,
            $posts,
            $page,
            $totalPages,
            $heading,
            section_lead($section),
            $secao,
            null
        ),
        [
            'canonical' => site_list_url($config, $page, $secao, null),
            'og_title' => $heading,
            'og_description' => section_lead($section),
            'og_type' => 'website',
            'og_url' => site_list_url($config, $page, $secao, null),
            'nav' => $secao,
        ],
        200,
        $recentPosts,
        $popularTags
    );
    exit;
}

$totalPosts = posts_count_published($pdo);
$totalPages = max(1, (int) ceil($totalPosts / POSTS_PER_PAGE));
if ($page > $totalPages && $totalPosts > 0) {
    render_vitrine_page(
        $config,
        t('ui.not_found'),
        render_not_found_content(),
        ['nav' => 'none'],
        404,
        $recentPosts,
        $popularTags
    );
    exit;
}

$posts = posts_attach_tags($pdo, posts_list_published($pdo, $page));
$pageTitle = $page > 1 ? t('ui.page', ['n' => (string) $page]) : i18n_t($pdo, 'site.home');
render_vitrine_page(
    $config,
    $pageTitle,
    render_home_content($config, $posts, $page, $totalPages, null, null, null, null, $pdo),
    [
        'canonical' => site_home_url($config, $page),
        'og_title' => $pageTitle,
        'og_description' => t('ui.og_home'),
        'og_type' => 'website',
        'og_url' => site_home_url($config, $page),
        'nav' => 'home',
    ],
    200,
    $recentPosts,
    $popularTags,
    [],
    render_calendar_ticker(calendar_ticker_items($pdo))
);
