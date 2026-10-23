<?php

declare(strict_types=1);

/**
 * @param array<string, mixed> $post
 * @return array<string, mixed>
 */
function api_serialize_post(array $config, array $post, bool $includeBody = true): array
{
    $id = (int) $post['id'];
    $out = [
        'id' => $id,
        'title' => (string) $post['title'],
        'section' => (string) ($post['section'] ?? 'news'),
        'status' => (string) ($post['status'] ?? 'draft'),
        'published_at' => $post['published_at'] !== null ? (string) $post['published_at'] : null,
        'url' => site_post_url($config, $id),
    ];
    if (array_key_exists('featured_video_url', $post)) {
        $out['featured_video_url'] = $post['featured_video_url'] !== null ? (string) $post['featured_video_url'] : null;
    }

    if ($includeBody) {
        $out['body'] = (string) ($post['body'] ?? '');
    }

    if (isset($post['translations']) && is_array($post['translations'])) {
        $out['translations'] = $post['translations'];
    }

    if (isset($post['tags']) && is_array($post['tags'])) {
        $out['tags'] = array_values(array_map(static function (array $tag): array {
            return [
                'slug' => (string) $tag['slug'],
                'name' => (string) $tag['name'],
            ];
        }, $post['tags']));
    }

    return $out;
}

/**
 * featured_video_url é opcional no payload: ausente = não altera; null/"" = remove.
 *
 * @param array<string, mixed> $payload
 * @return array{present: bool, url: ?string}
 */
function api_read_featured_video(array $payload): array
{
    if (!array_key_exists('featured_video_url', $payload)) {
        return ['present' => false, 'url' => null];
    }
    $raw = trim((string) ($payload['featured_video_url'] ?? ''));
    if ($raw === '') {
        return ['present' => true, 'url' => null];
    }
    $videoId = youtube_video_id($raw);
    if ($videoId === null) {
        api_error(400, 'featured_video_url must be a YouTube URL');
    }

    return ['present' => true, 'url' => 'https://www.youtube.com/watch?v=' . $videoId];
}

function api_handle_posts_collection(PDO $pdo, array $config): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_handle_posts_list($pdo, $config);
    }
    if ($method === 'POST') {
        api_handle_posts_create($pdo, $config);
    }
    api_error(405, 'Method not allowed');
}

function api_handle_posts_item(PDO $pdo, array $config, int $id): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_handle_posts_get($pdo, $config, $id);
    }
    if ($method === 'POST' || $method === 'PATCH') {
        // api_handle_posts_update exige POST; alinhar método
        $_SERVER['REQUEST_METHOD'] = 'POST';
        api_handle_posts_update($pdo, $config, $id);
    }
    api_error(405, 'Method not allowed');
}

function api_handle_posts_list(PDO $pdo, array $config): never
{
    api_require_https($config);
    api_require_auth($config);

    $page = max(1, (int) ($_GET['page'] ?? 1));
    $perPage = max(1, min(100, (int) ($_GET['per_page'] ?? 20)));

    $sectionRaw = isset($_GET['section']) ? strtolower(trim((string) $_GET['section'])) : '';
    $section = null;
    if ($sectionRaw !== '' && $sectionRaw !== 'all') {
        $section = section_normalize($sectionRaw);
        if (!in_array($section, POST_SECTIONS, true)) {
            api_error(400, 'Invalid section');
        }
    }

    $statusRaw = isset($_GET['status']) ? strtolower(trim((string) $_GET['status'])) : '';
    $status = null;
    if ($statusRaw !== '' && $statusRaw !== 'all') {
        if (!in_array($statusRaw, ['draft', 'published'], true)) {
            api_error(400, 'Invalid status');
        }
        $status = $statusRaw;
    }

    $q = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
    if ($q === '') {
        $q = null;
    }

    $total = posts_count_for_api($pdo, $section, $status, $q);
    $rows = posts_list_for_api($pdo, $page, $perPage, $section, $status, $q);
    $tagged = posts_attach_tags($pdo, $rows);

    $posts = [];
    foreach ($tagged as $post) {
        $posts[] = api_serialize_post($config, $post, false);
    }

    api_json(200, [
        'posts' => $posts,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $total,
        'total_pages' => max(1, (int) ceil($total / max(1, $perPage))),
    ]);
}

function api_handle_posts_get(PDO $pdo, array $config, int $id): never
{
    api_require_https($config);
    api_require_auth($config);

    if ($id <= 0) {
        api_error(400, 'Invalid post id');
    }

    $post = posts_get_by_id($pdo, $id);
    if ($post === null) {
        api_error(404, 'Post not found');
    }

    $tagged = posts_attach_tags($pdo, [$post]);
    $tagged[0]['translations'] = post_translations_list($pdo, $id);
    api_json(200, [
        'post' => api_serialize_post($config, $tagged[0], true),
    ]);
}

function api_handle_posts_create(PDO $pdo, array $config): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);

    $payload = api_read_json_body();
    $title = api_normalize_title((string) ($payload['title'] ?? ''));
    $body = api_sanitize_html(normalize_article_body_html((string) ($payload['body'] ?? '')));
    $status = api_normalize_status((string) ($payload['status'] ?? 'published'));
    $section = api_normalize_section($payload['section'] ?? 'news');
    $publishedAt = api_normalize_published_at(
        isset($payload['published_at']) ? (string) $payload['published_at'] : null,
        $status
    );

    if ($body === '') {
        api_error(400, 'Body is required');
    }

    $featuredVideo = api_read_featured_video($payload);
    $tags = api_normalize_tags($payload['tags'] ?? null, $title, $body);
    $id = posts_create($pdo, $title, $body, $status, $publishedAt, $section);
    if ($featuredVideo['present']) {
        posts_set_featured_video($pdo, $id, $featuredVideo['url']);
    }
    tags_sync_for_post($pdo, $id, $tags);
    if ($status === 'published') {
        try {
            push_notify_new_post($pdo, $config, $id);
        } catch (Throwable $e) {
            error_log('push_notify_new_post: ' . $e->getMessage());
        }
    }
    api_post_response($config, $id);
}

function api_handle_posts_update(PDO $pdo, array $config, int $id): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);

    if ($id <= 0) {
        api_error(400, 'Invalid post id');
    }

    $existing = posts_get_by_id($pdo, $id);
    if ($existing === null) {
        api_error(404, 'Post not found');
    }
    $wasPublished = (string) ($existing['status'] ?? '') === 'published';

    $payload = api_read_json_body();
    $featuredVideo = api_read_featured_video($payload);

    // Só o vídeo destacado (Manager → "Salvar escolha de imagem" em post já publicado).
    if ($featuredVideo['present'] && !array_key_exists('title', $payload) && !array_key_exists('body', $payload)) {
        posts_set_featured_video($pdo, $id, $featuredVideo['url']);
        api_post_response($config, $id);
    }

    $title = api_normalize_title((string) ($payload['title'] ?? ''));
    $body = api_sanitize_html(normalize_article_body_html((string) ($payload['body'] ?? '')));
    $status = api_normalize_status((string) ($payload['status'] ?? 'published'));
    $section = api_normalize_section($payload['section'] ?? 'news');
    $publishedAt = api_normalize_published_at(
        isset($payload['published_at']) ? (string) $payload['published_at'] : null,
        $status
    );

    if ($body === '') {
        api_error(400, 'Body is required');
    }

    $tags = api_normalize_tags($payload['tags'] ?? null, $title, $body);
    posts_update($pdo, $id, $title, $body, $status, $publishedAt, $section);
    if ($featuredVideo['present']) {
        posts_set_featured_video($pdo, $id, $featuredVideo['url']);
    }
    tags_sync_for_post($pdo, $id, $tags);
    if ($status === 'published' && !$wasPublished) {
        try {
            push_notify_new_post($pdo, $config, $id);
        } catch (Throwable $e) {
            error_log('push_notify_new_post: ' . $e->getMessage());
        }
    }
    api_post_response($config, $id);
}

function api_handle_post_translation_item(PDO $pdo, array $config, int $id, string $lang): never
{
    api_require_https($config);
    api_require_auth($config);

    if ($id <= 0) {
        api_error(400, 'Invalid post id');
    }

    $lang = normalize_site_lang($lang);
    if (!in_array($lang, ['en', 'es'], true)) {
        api_error(400, 'Invalid language');
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        $translation = post_translation_get($pdo, $id, $lang);
        api_json(200, [
            'translation' => $translation !== null ? [
                'lang' => (string) $translation['lang'],
                'title' => (string) $translation['title'],
                'body' => normalize_article_body_html((string) $translation['body']),
                'status' => (string) $translation['status'],
                'updated_at' => (string) ($translation['updated_at'] ?? ''),
            ] : null,
        ]);
    }

    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        $payload = api_read_json_body();
        $title = api_normalize_title((string) ($payload['title'] ?? ''));
        $body = api_sanitize_html(normalize_article_body_html((string) ($payload['body'] ?? '')));
        $status = api_normalize_status((string) ($payload['status'] ?? 'published'));

        if ($body === '') {
            api_error(400, 'Body is required');
        }
        if (posts_get_by_id($pdo, $id) === null) {
            api_error(404, 'Post not found');
        }

        post_translation_upsert($pdo, $id, $lang, $title, $body, $status);
        api_json(200, ['ok' => true]);
    }

    api_error(405, 'Method not allowed');
}

function api_handle_i18n_settings(PDO $pdo, array $config): never
{
    api_require_https($config);
    api_require_auth($config);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_json(200, ['settings' => section_translations($pdo)]);
    }

    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        $payload = api_read_json_body();
        $input = $payload['settings'] ?? null;
        if (!is_array($input)) {
            api_error(400, 'settings must be an object');
        }

        $next = default_section_translations();
        foreach ($input as $key => $langs) {
            if (!is_string($key) || !is_array($langs)) {
                continue;
            }
            foreach (['pt', 'en', 'es'] as $lang) {
                $value = $langs[$lang] ?? null;
                if (is_string($value) && trim($value) !== '') {
                    $next[$key][$lang] = trim($value);
                }
            }
        }

        site_settings_put($pdo, SITE_I18N_SETTING_KEY, $next);
        api_json(200, ['settings' => $next]);
    }

    api_error(405, 'Method not allowed');
}

function api_handle_root(): never
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET') {
        api_json(405, ['error' => 'Method not allowed']);
    }

    api_error(405, 'Method not allowed');
}
