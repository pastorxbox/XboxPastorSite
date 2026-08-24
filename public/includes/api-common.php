<?php

declare(strict_types=1);

const API_MAX_BYTES = 8 * 1024 * 1024;

function api_json(int $statusCode, array $payload): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function api_error(int $statusCode, string $message): never
{
    api_json($statusCode, ['error' => $message]);
}

function api_require_post(): void
{
    if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        api_error(405, 'Method not allowed');
    }
}

function api_is_local_request(array $config): bool
{
    $base = (string) ($config['site_base_url'] ?? '');
    if ($base === '') {
        return true;
    }

    $host = parse_url($base, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return true;
    }

    $host = strtolower($host);

    return in_array($host, ['localhost', '127.0.0.1', '::1'], true);
}

function api_require_https(array $config): void
{
    if (api_is_local_request($config)) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    if (!$https) {
        api_error(403, 'HTTPS required');
    }
}

function api_require_auth(array $config): void
{
    $expected = (string) ($config['site_api_key'] ?? '');
    if ($expected === '') {
        api_error(500, 'API key not configured');
    }

    $header = (string) ($_SERVER['HTTP_AUTHORIZATION'] ?? '');
    if ($header === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $name => $value) {
            if (strcasecmp((string) $name, 'Authorization') === 0) {
                $header = (string) $value;
                break;
            }
        }
    }

    if (!preg_match('/^Bearer\s+(\S+)\s*$/i', $header, $matches)) {
        api_error(401, 'Unauthorized');
    }

    if (!hash_equals($expected, $matches[1])) {
        api_error(401, 'Unauthorized');
    }
}

function api_validate_content_length(int $maxBytes = API_MAX_BYTES): void
{
    $length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($length > $maxBytes) {
        api_error(413, 'Payload too large');
    }
}

function api_require_json_content_type(): void
{
    $contentType = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''))[0]));
    if ($contentType !== 'application/json') {
        api_error(415, 'Content-Type must be application/json');
    }
}

/**
 * @return array<string, mixed>
 */
function api_read_json_body(int $maxBytes = API_MAX_BYTES): array
{
    api_validate_content_length($maxBytes);
    api_require_json_content_type();

    $raw = file_get_contents('php://input');
    if ($raw === false || strlen($raw) > $maxBytes) {
        api_error(413, 'Payload too large');
    }

    if ($raw === '') {
        api_error(400, 'Empty JSON body');
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        api_error(400, 'Invalid JSON body');
    }

    return $data;
}

function api_sanitize_html(string $html): string
{
    static $purifier = null;
    if ($purifier === null) {
        if (!class_exists(HTMLPurifier::class)) {
            api_error(500, 'HTMLPurifier unavailable');
        }

        $config = HTMLPurifier_Config::createDefault();
        $config->set('Cache.DefinitionImpl', null);
        $config->set(
            'HTML.Allowed',
            'p,br,strong,em,a[href|title|target|rel],ul,ol,li,blockquote,h2,h3,h4,img[src|alt|width|height]'
        );
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $purifier = new HTMLPurifier($config);
    }

    return $purifier->purify($html);
}

function api_normalize_title(string $title): string
{
    $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
    if ($title === '') {
        api_error(400, 'Title is required');
    }

    if (function_exists('mb_strlen') && mb_strlen($title) > 500) {
        api_error(400, 'Title exceeds 500 characters');
    } elseif (strlen($title) > 500) {
        api_error(400, 'Title exceeds 500 characters');
    }

    return $title;
}

function api_normalize_status(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === '') {
        return 'published';
    }

    if (!in_array($status, ['draft', 'published'], true)) {
        api_error(400, 'Invalid status');
    }

    return $status;
}

function api_normalize_published_at(?string $value, string $status): ?string
{
    if ($status !== 'published') {
        return null;
    }

    if ($value === null || trim($value) === '') {
        return date('Y-m-d H:i:s');
    }

    $timestamp = strtotime($value);
    if ($timestamp === false) {
        api_error(400, 'Invalid published_at');
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function api_post_response(array $config, int $id): never
{
    api_json(200, [
        'id' => $id,
        'url' => site_post_url($config, $id),
    ]);
}

function api_route_path(): string
{
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH) ?? '');
    $path = rtrim($path, '/');
    if ($path === '') {
        return '/';
    }

    return $path;
}
