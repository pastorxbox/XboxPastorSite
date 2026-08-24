<?php

declare(strict_types=1);

function api_handle_posts_create(PDO $pdo, array $config): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);

    $payload = api_read_json_body();
    $title = api_normalize_title((string) ($payload['title'] ?? ''));
    $body = api_sanitize_html((string) ($payload['body'] ?? ''));
    $status = api_normalize_status((string) ($payload['status'] ?? 'published'));
    $publishedAt = api_normalize_published_at(
        isset($payload['published_at']) ? (string) $payload['published_at'] : null,
        $status
    );

    if ($body === '') {
        api_error(400, 'Body is required');
    }

    $id = posts_create($pdo, $title, $body, $status, $publishedAt);
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

    if (posts_get_by_id($pdo, $id) === null) {
        api_error(404, 'Post not found');
    }

    $payload = api_read_json_body();
    $title = api_normalize_title((string) ($payload['title'] ?? ''));
    $body = api_sanitize_html((string) ($payload['body'] ?? ''));
    $status = api_normalize_status((string) ($payload['status'] ?? 'published'));
    $publishedAt = api_normalize_published_at(
        isset($payload['published_at']) ? (string) $payload['published_at'] : null,
        $status
    );

    if ($body === '') {
        api_error(400, 'Body is required');
    }

    posts_update($pdo, $id, $title, $body, $status, $publishedAt);
    api_post_response($config, $id);
}

function api_handle_root(): never
{
    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    if ($method === 'GET') {
        api_json(405, ['error' => 'Method not allowed']);
    }

    api_error(405, 'Method not allowed');
}
