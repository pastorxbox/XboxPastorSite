<?php

declare(strict_types=1);

require_once __DIR__ . '/tvxbox.php';

function api_tvxbox_response(int $id): never
{
    api_json(200, [
        'type' => 'tvxbox',
        'id' => $id,
    ]);
}

/**
 * @return array{
 *   youtube_url: string,
 *   youtube_id: string,
 *   tag: string,
 *   comment: string,
 *   show_on_tv: int,
 *   expires_at: ?string,
 *   gp_release_id: ?int,
 *   status: string
 * }
 */
function api_parse_tvxbox_payload(PDO $pdo, array $payload): array
{
    $url = api_optional_string($payload['youtube_url'] ?? ($payload['url'] ?? null), 500);
    if ($url === null) {
        api_error(400, 'youtube_url is required');
    }
    if (!preg_match('#^https?://#i', $url) && preg_match('/^[A-Za-z0-9_-]{11}$/', $url) !== 1) {
        api_error(400, 'youtube_url must be http(s) or a YouTube id');
    }

    $youtubeId = tvxbox_parse_youtube_id($url);
    if ($youtubeId === null) {
        api_error(400, 'Could not parse YouTube video id');
    }

    if (!preg_match('#^https?://#i', $url)) {
        $url = 'https://www.youtube.com/watch?v=' . $youtubeId;
    }

    $tag = api_optional_string($payload['tag'] ?? null, 40) ?? '';
    $comment = api_optional_string($payload['comment'] ?? null, 255) ?? '';
    $showOnTv = array_key_exists('show_on_tv', $payload)
        ? api_bool_flag($payload['show_on_tv'])
        : 1;
    $status = api_normalize_status((string) ($payload['status'] ?? 'published'));

    $expiresAt = null;
    if (array_key_exists('expires_at', $payload)) {
        $rawExpires = $payload['expires_at'];
        if ($rawExpires !== null && $rawExpires !== '' && $rawExpires !== false) {
            if (!is_string($rawExpires) && !is_numeric($rawExpires)) {
                api_error(400, 'Invalid expires_at');
            }
            $expiresStr = trim((string) $rawExpires);
            if ($expiresStr !== '') {
                if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $expiresStr, $m) === 1) {
                    $timestamp = strtotime($m[1] . ' 00:00:00');
                } else {
                    $timestamp = strtotime($expiresStr);
                }
                if ($timestamp === false) {
                    api_error(400, 'Invalid expires_at');
                }
                $expiresAt = date('Y-m-d', $timestamp);
            }
        }
    }

    $gpReleaseId = null;
    if (array_key_exists('gp_release_id', $payload)) {
        $raw = $payload['gp_release_id'];
        if ($raw !== null && $raw !== '' && $raw !== false) {
            $gpReleaseId = (int) $raw;
            if ($gpReleaseId <= 0) {
                api_error(400, 'Invalid gp_release_id');
            }
            if (calendar_get_gamepass($pdo, $gpReleaseId) === null) {
                api_error(400, 'Game Pass release not found');
            }
        }
    }

    return [
        'youtube_url' => $url,
        'youtube_id' => $youtubeId,
        'tag' => $tag,
        'comment' => $comment,
        'show_on_tv' => $showOnTv,
        'expires_at' => $expiresAt,
        'gp_release_id' => $gpReleaseId,
        'status' => $status,
    ];
}

function api_handle_tvxbox_collection(PDO $pdo, array $config): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_handle_tvxbox_list($pdo, $config);
    }
    if ($method === 'POST') {
        api_handle_tvxbox_create($pdo, $config);
    }
    api_error(405, 'Method not allowed');
}

function api_handle_tvxbox_item(PDO $pdo, array $config, int $id): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_handle_tvxbox_get($pdo, $config, $id);
    }
    if ($method === 'DELETE') {
        api_handle_tvxbox_delete($pdo, $config, $id);
    }
    if ($method === 'POST' || $method === 'PATCH') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        api_handle_tvxbox_update($pdo, $config, $id);
    }
    api_error(405, 'Method not allowed');
}

function api_handle_tvxbox_list(PDO $pdo, array $config): never
{
    api_require_method('GET');
    api_require_https($config);
    api_require_auth($config);
    api_json(200, ['items' => tvxbox_list_admin($pdo)]);
}

function api_handle_tvxbox_get(PDO $pdo, array $config, int $id): never
{
    api_require_method('GET');
    api_require_https($config);
    api_require_auth($config);
    $item = tvxbox_get($pdo, $id);
    if ($item === null) {
        api_error(404, 'TV Xbox video not found');
    }
    api_json(200, ['item' => $item]);
}

function api_handle_tvxbox_create(PDO $pdo, array $config): never
{
    api_require_method('POST');
    api_require_https($config);
    api_require_auth($config);
    $data = api_parse_tvxbox_payload($pdo, api_read_json_body());
    $id = tvxbox_create($pdo, $data);
    api_tvxbox_response($id);
}

function api_handle_tvxbox_update(PDO $pdo, array $config, int $id): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);
    $existing = tvxbox_get($pdo, $id);
    if ($id <= 0 || $existing === null) {
        api_error(404, 'TV Xbox video not found');
    }
    $payload = api_read_json_body();
    $data = api_parse_tvxbox_payload($pdo, $payload);
    if (!array_key_exists('expires_at', $payload)) {
        $data['expires_at'] = isset($existing['expires_at']) && is_string($existing['expires_at'])
            ? $existing['expires_at']
            : null;
    }
    tvxbox_update($pdo, $id, $data);
    api_tvxbox_response($id);
}

function api_handle_tvxbox_delete(PDO $pdo, array $config, int $id): never
{
    api_require_method('DELETE');
    api_require_https($config);
    api_require_auth($config);
    if ($id <= 0 || tvxbox_get($pdo, $id) === null) {
        api_error(404, 'TV Xbox video not found');
    }
    tvxbox_delete($pdo, $id);
    api_tvxbox_response($id);
}

/**
 * POST /api/tvxbox/link — body: { gp_release_id, tv_video_id|null }
 */
function api_handle_tvxbox_link(PDO $pdo, array $config): never
{
    api_require_method('POST');
    api_require_https($config);
    api_require_auth($config);

    $payload = api_read_json_body();
    $releaseId = (int) ($payload['gp_release_id'] ?? 0);
    if ($releaseId <= 0 || calendar_get_gamepass($pdo, $releaseId) === null) {
        api_error(400, 'gp_release_id is required');
    }

    $videoId = null;
    if (array_key_exists('tv_video_id', $payload) && $payload['tv_video_id'] !== null && $payload['tv_video_id'] !== '') {
        $videoId = (int) $payload['tv_video_id'];
        if ($videoId <= 0 || tvxbox_get($pdo, $videoId) === null) {
            api_error(400, 'tv_video_id not found');
        }
    }

    tvxbox_link_to_release($pdo, $releaseId, $videoId);
    api_json(200, [
        'type' => 'tvxbox_link',
        'gp_release_id' => $releaseId,
        'tv_video_id' => $videoId,
    ]);
}

function api_handle_tvxbox_translation_item(PDO $pdo, array $config, int $id, string $lang): never
{
    api_require_https($config);
    api_require_auth($config);

    if ($id <= 0) {
        api_error(400, 'Invalid id');
    }

    $lang = normalize_site_lang($lang);
    if (!in_array($lang, ['en', 'es'], true)) {
        api_error(400, 'Invalid language');
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        $translation = tvxbox_translation_get($pdo, $id, $lang);
        api_json(200, [
            'translation' => $translation !== null ? [
                'lang' => (string) $translation['lang'],
                'tag' => (string) ($translation['tag'] ?? ''),
                'comment' => (string) ($translation['comment'] ?? ''),
                'status' => (string) ($translation['status'] ?? 'published'),
                'updated_at' => (string) ($translation['updated_at'] ?? ''),
            ] : null,
        ]);
    }

    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        if (tvxbox_get($pdo, $id) === null) {
            api_error(404, 'TV Xbox video not found');
        }
        $payload = api_read_json_body();
        $tag = trim((string) ($payload['tag'] ?? ''));
        $comment = trim((string) ($payload['comment'] ?? ''));
        $status = api_normalize_status((string) ($payload['status'] ?? 'published'));

        if ($tag === '' && $comment === '') {
            api_error(400, 'tag or comment is required');
        }

        tvxbox_translation_upsert($pdo, $id, $lang, $tag, $comment, $status);
        api_json(200, ['ok' => true]);
    }

    api_error(405, 'Method not allowed');
}
