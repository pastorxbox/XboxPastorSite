<?php

declare(strict_types=1);

function api_bool_flag(mixed $value): int
{
    if (is_bool($value)) {
        return $value ? 1 : 0;
    }
    if (is_int($value) || is_float($value)) {
        return ((int) $value) !== 0 ? 1 : 0;
    }
    if (is_string($value)) {
        $v = strtolower(trim($value));
        return in_array($v, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    return 0;
}

function api_optional_string(mixed $value, int $maxLen = 500): ?string
{
    if ($value === null) {
        return null;
    }
    if (!is_string($value)) {
        api_error(400, 'Invalid string field');
    }
    $value = trim($value);
    if ($value === '') {
        return null;
    }
    if (function_exists('mb_strlen') ? mb_strlen($value) > $maxLen : strlen($value) > $maxLen) {
        api_error(400, 'Field exceeds max length');
    }

    return $value;
}

function api_require_datetime(mixed $value, string $field): string
{
    if (!is_string($value) || trim($value) === '') {
        api_error(400, $field . ' is required');
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        api_error(400, 'Invalid ' . $field);
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function api_calendar_response(string $type, int $id): never
{
    api_json(200, [
        'type' => $type,
        'id' => $id,
    ]);
}

/**
 * @return array<string, mixed>
 */
function api_parse_gamepass_payload(array $payload): array
{
    $title = api_normalize_title((string) ($payload['title'] ?? ''));
    $status = api_normalize_status((string) ($payload['status'] ?? 'published'));
    $bodyRaw = (string) ($payload['body'] ?? '');
    $body = $bodyRaw !== '' ? api_sanitize_html(normalize_article_body_html($bodyRaw)) : null;

    $onUltimate = api_bool_flag($payload['on_ultimate'] ?? false);
    $onPremium = api_bool_flag($payload['on_premium'] ?? false);
    $onEssential = api_bool_flag($payload['on_essential'] ?? false);
    $onPc = api_bool_flag($payload['on_pc'] ?? false);
    $onGamepass = array_key_exists('on_gamepass', $payload)
        ? api_bool_flag($payload['on_gamepass'])
        : (($onUltimate || $onPremium || $onEssential || $onPc) ? 1 : 0);
    if ($onGamepass === 0) {
        $onUltimate = 0;
        $onPremium = 0;
        $onEssential = 0;
        $onPc = 0;
    }

    $storeUrl = api_optional_string(
        $payload['store_url']
            ?? ($payload['storeUrl'] ?? null)
            ?? ($payload['external_url'] ?? null),
        500
    );
    if ($storeUrl !== null && !preg_match('#^https?://#i', $storeUrl)) {
        api_error(400, 'store_url must be http or https');
    }

    $trailerUrl = api_optional_string(
        $payload['trailer_url'] ?? ($payload['trailerUrl'] ?? ($payload['youtube_url'] ?? null)),
        500
    );
    if ($trailerUrl !== null && !preg_match('#^https?://#i', $trailerUrl)) {
        api_error(400, 'trailer_url must be http or https');
    }

    $priceRaw = $payload['price_brl'] ?? ($payload['priceBRL'] ?? ($payload['price'] ?? null));
    $priceBrl = null;
    if ($priceRaw !== null && $priceRaw !== '') {
        if (is_numeric($priceRaw)) {
            $priceBrl = round((float) $priceRaw, 2);
        } elseif (is_string($priceRaw)) {
            $normalized = preg_replace('/[^\d,.\-]/', '', $priceRaw) ?? '';
            if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } elseif (str_contains($normalized, ',')) {
                $normalized = str_replace(',', '.', $normalized);
            }
            $priceBrl = is_numeric($normalized) ? round((float) $normalized, 2) : null;
        }
        if ($priceBrl !== null && $priceBrl < 0) {
            $priceBrl = null;
        }
    }

    return [
        'title' => $title,
        'slug' => api_optional_string($payload['slug'] ?? null, 255),
        'cover_url' => api_optional_string($payload['cover_url'] ?? null, 500),
        'store_url' => $storeUrl,
        'price_brl' => $priceBrl,
        'trailer_url' => $trailerUrl,
        'release_at' => api_require_datetime($payload['release_at'] ?? null, 'release_at'),
        'body' => $body,
        'on_gamepass' => $onGamepass,
        'on_ultimate' => $onUltimate,
        'on_premium' => $onPremium,
        'on_essential' => $onEssential,
        'on_pc' => $onPc,
        'platform_console' => api_bool_flag($payload['platform_console'] ?? false),
        'platform_pc' => api_bool_flag($payload['platform_pc'] ?? false),
        'platform_cloud' => api_bool_flag($payload['platform_cloud'] ?? false),
        'play_anywhere' => api_bool_flag(
            $payload['play_anywhere'] ?? ($payload['playAnywhere'] ?? false)
        ),
        'supports_pt_br' => api_bool_flag(
            $payload['supports_pt_br']
                ?? ($payload['supportsPtBr'] ?? ($payload['lang_pt_br'] ?? false))
        ),
        'status' => $status,
        'sort_order' => (int) ($payload['sort_order'] ?? 0),
        'show_on_tv' => array_key_exists('show_on_tv', $payload)
            ? api_bool_flag($payload['show_on_tv'])
            : 0,
    ];
}

/**
 * @return array<string, mixed>
 */
function api_parse_event_payload(array $payload): array
{
    $title = api_normalize_title((string) ($payload['title'] ?? ''));
    $status = api_normalize_status((string) ($payload['status'] ?? 'published'));
    $bodyRaw = (string) ($payload['body'] ?? '');
    $body = $bodyRaw !== '' ? api_sanitize_html(normalize_article_body_html($bodyRaw)) : null;
    $startsAt = api_require_datetime($payload['starts_at'] ?? null, 'starts_at');
    $endsAt = api_require_datetime($payload['ends_at'] ?? null, 'ends_at');

    $startDay = new DateTimeImmutable(date('Y-m-d', strtotime($startsAt)));
    $endDay = new DateTimeImmutable(date('Y-m-d', strtotime($endsAt)));
    if ($endDay < $startDay) {
        api_error(400, 'ends_at must be on or after starts_at');
    }
    $spanDays = ((int) $startDay->diff($endDay)->format('%a')) + 1;
    if ($spanDays > 7) {
        api_error(400, 'Event duration cannot exceed 7 days');
    }

    return [
        'title' => $title,
        'banner_url' => api_optional_string($payload['banner_url'] ?? null, 500),
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'body' => $body,
        'external_url' => api_optional_string($payload['external_url'] ?? null, 500),
        'status' => $status,
    ];
}

/**
 * @return array<string, mixed>
 */
function api_parse_history_payload(array $payload): array
{
    $title = api_normalize_title((string) ($payload['title'] ?? ''));
    $status = api_normalize_status((string) ($payload['status'] ?? 'published'));
    $year = (int) ($payload['occurred_year'] ?? 0);
    $month = (int) ($payload['occurred_month'] ?? 0);
    $day = (int) ($payload['occurred_day'] ?? 0);
    if ($year < 1970 || $year > 2100) {
        api_error(400, 'Invalid occurred_year');
    }
    if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
        api_error(400, 'Invalid occurred_month/day');
    }
    if (!checkdate($month, $day, $year > 0 ? $year : 2000)) {
        api_error(400, 'Invalid calendar date');
    }

    $bodyRaw = (string) ($payload['body'] ?? '');
    $body = $bodyRaw !== '' ? trim(strip_tags(str_replace(["\r\n", "\r"], "\n", $bodyRaw))) : null;

    return [
        'title' => $title,
        'occurred_year' => $year,
        'occurred_month' => $month,
        'occurred_day' => $day,
        'context' => api_optional_string($payload['context'] ?? null, 255),
        'body' => $body,
        'cover_url' => api_optional_string($payload['cover_url'] ?? null, 500),
        'status' => $status,
    ];
}

function api_handle_gamepass_create(PDO $pdo, array $config): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);
    $data = api_parse_gamepass_payload(api_read_json_body());
    $showOnTv = (int) ($data['show_on_tv'] ?? 0) === 1;
    $data['show_on_tv'] = $showOnTv ? 1 : 0;
    $id = calendar_create_gamepass($pdo, $data);
    if (function_exists('tvxbox_sync_release_trailer')) {
        tvxbox_sync_release_trailer($pdo, $id, $data['trailer_url'] ?? null, $showOnTv);
    }
    api_calendar_response('gamepass', $id);
}

function api_handle_gamepass_list(PDO $pdo, array $config): never
{
    api_require_https($config);
    api_require_auth($config);
    $items = calendar_list_gamepass_admin($pdo);
    if (function_exists('tvxbox_enrich_gamepass_items')) {
        $items = tvxbox_enrich_gamepass_items($pdo, $items);
    }
    api_json(200, ['items' => $items]);
}

function api_handle_gamepass_get(PDO $pdo, array $config, int $id): never
{
    api_require_https($config);
    api_require_auth($config);
    $row = calendar_get_gamepass($pdo, $id);
    if ($row === null) {
        api_error(404, 'Game Pass release not found');
    }
    if (function_exists('tvxbox_enrich_gamepass_items')) {
        $row = tvxbox_enrich_gamepass_items($pdo, [$row])[0];
    }
    api_json(200, ['item' => $row]);
}

function api_handle_gamepass_delete(PDO $pdo, array $config, int $id): never
{
    api_require_method('DELETE');
    api_require_https($config);
    api_require_auth($config);
    if ($id <= 0 || calendar_get_gamepass($pdo, $id) === null) {
        api_error(404, 'Game Pass release not found');
    }
    calendar_delete_gamepass($pdo, $id);
    api_calendar_response('gamepass', $id);
}

function api_handle_gamepass_collection(PDO $pdo, array $config): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_handle_gamepass_list($pdo, $config);
    }
    if ($method === 'POST') {
        api_handle_gamepass_create($pdo, $config);
    }
    api_error(405, 'Method not allowed');
}

function api_handle_gamepass_item(PDO $pdo, array $config, int $id): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_handle_gamepass_get($pdo, $config, $id);
    }
    if ($method === 'DELETE') {
        api_handle_gamepass_delete($pdo, $config, $id);
    }
    if ($method === 'POST' || $method === 'PATCH') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        api_handle_gamepass_update($pdo, $config, $id);
    }
    api_error(405, 'Method not allowed');
}

function api_handle_gamepass_update(PDO $pdo, array $config, int $id): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);
    if ($id <= 0 || calendar_get_gamepass($pdo, $id) === null) {
        api_error(404, 'Game Pass release not found');
    }
    $data = api_parse_gamepass_payload(api_read_json_body());
    $showOnTv = (int) ($data['show_on_tv'] ?? 0) === 1;
    $data['show_on_tv'] = $showOnTv ? 1 : 0;
    calendar_update_gamepass($pdo, $id, $data);
    if (function_exists('tvxbox_sync_release_trailer')) {
        tvxbox_sync_release_trailer($pdo, $id, $data['trailer_url'] ?? null, $showOnTv);
    }
    api_calendar_response('gamepass', $id);
}

function api_handle_event_create(PDO $pdo, array $config): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);
    $data = api_parse_event_payload(api_read_json_body());
    $id = calendar_create_event($pdo, $data);
    api_calendar_response('event', $id);
}

function api_handle_event_list(PDO $pdo, array $config): never
{
    api_require_https($config);
    api_require_auth($config);
    api_json(200, ['items' => calendar_list_events_admin($pdo)]);
}

function api_handle_event_get(PDO $pdo, array $config, int $id): never
{
    api_require_https($config);
    api_require_auth($config);
    $row = calendar_get_event($pdo, $id);
    if ($row === null) {
        api_error(404, 'Event not found');
    }
    api_json(200, ['item' => $row]);
}

function api_handle_event_delete(PDO $pdo, array $config, int $id): never
{
    api_require_method('DELETE');
    api_require_https($config);
    api_require_auth($config);
    if ($id <= 0 || calendar_get_event($pdo, $id) === null) {
        api_error(404, 'Event not found');
    }
    calendar_delete_event($pdo, $id);
    api_calendar_response('event', $id);
}

function api_handle_event_collection(PDO $pdo, array $config): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_handle_event_list($pdo, $config);
    }
    if ($method === 'POST') {
        api_handle_event_create($pdo, $config);
    }
    api_error(405, 'Method not allowed');
}

function api_handle_event_item(PDO $pdo, array $config, int $id): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_handle_event_get($pdo, $config, $id);
    }
    if ($method === 'DELETE') {
        api_handle_event_delete($pdo, $config, $id);
    }
    if ($method === 'POST' || $method === 'PATCH') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        api_handle_event_update($pdo, $config, $id);
    }
    api_error(405, 'Method not allowed');
}

function api_handle_event_update(PDO $pdo, array $config, int $id): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);
    if ($id <= 0 || calendar_get_event($pdo, $id) === null) {
        api_error(404, 'Event not found');
    }
    $data = api_parse_event_payload(api_read_json_body());
    calendar_update_event($pdo, $id, $data);
    api_calendar_response('event', $id);
}

function api_handle_history_create(PDO $pdo, array $config): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);
    $data = api_parse_history_payload(api_read_json_body());
    $id = calendar_create_history($pdo, $data);
    api_calendar_response('history', $id);
}

function api_handle_history_list(PDO $pdo, array $config): never
{
    api_require_https($config);
    api_require_auth($config);
    api_json(200, ['items' => calendar_list_history_admin($pdo)]);
}

function api_handle_history_get(PDO $pdo, array $config, int $id): never
{
    api_require_https($config);
    api_require_auth($config);
    $row = calendar_get_history($pdo, $id);
    if ($row === null) {
        api_error(404, 'History item not found');
    }
    api_json(200, ['item' => $row]);
}

function api_handle_history_delete(PDO $pdo, array $config, int $id): never
{
    api_require_method('DELETE');
    api_require_https($config);
    api_require_auth($config);
    if ($id <= 0 || calendar_get_history($pdo, $id) === null) {
        api_error(404, 'History item not found');
    }
    calendar_delete_history($pdo, $id);
    api_calendar_response('history', $id);
}

function api_handle_history_collection(PDO $pdo, array $config): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_handle_history_list($pdo, $config);
    }
    if ($method === 'POST') {
        api_handle_history_create($pdo, $config);
    }
    api_error(405, 'Method not allowed');
}

function api_handle_history_item(PDO $pdo, array $config, int $id): never
{
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        api_handle_history_get($pdo, $config, $id);
    }
    if ($method === 'DELETE') {
        api_handle_history_delete($pdo, $config, $id);
    }
    if ($method === 'POST' || $method === 'PATCH') {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        api_handle_history_update($pdo, $config, $id);
    }
    api_error(405, 'Method not allowed');
}

function api_handle_history_update(PDO $pdo, array $config, int $id): never
{
    api_require_post();
    api_require_https($config);
    api_require_auth($config);
    if ($id <= 0 || calendar_get_history($pdo, $id) === null) {
        api_error(404, 'History item not found');
    }
    $data = api_parse_history_payload(api_read_json_body());
    calendar_update_history($pdo, $id, $data);
    api_calendar_response('history', $id);
}

function api_calendar_entity_exists(PDO $pdo, string $entityType, int $id): bool
{
    return match ($entityType) {
        'gamepass' => calendar_get_gamepass($pdo, $id) !== null,
        'event' => calendar_get_event($pdo, $id) !== null,
        'history' => calendar_get_history($pdo, $id) !== null,
        default => false,
    };
}

function api_handle_calendar_translation_item(PDO $pdo, array $config, string $entityType, int $id, string $lang): never
{
    api_require_https($config);
    api_require_auth($config);

    $entityType = calendar_normalize_entity_type($entityType);
    if ($entityType === '') {
        api_error(400, 'Invalid calendar entity type');
    }
    if ($id <= 0) {
        api_error(400, 'Invalid id');
    }

    $lang = normalize_site_lang($lang);
    if (!in_array($lang, ['en', 'es'], true)) {
        api_error(400, 'Invalid language');
    }

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    if ($method === 'GET') {
        $translation = calendar_translation_get($pdo, $entityType, $id, $lang);
        api_json(200, [
            'translation' => $translation !== null ? [
                'lang' => (string) $translation['lang'],
                'title' => (string) $translation['title'],
                'body' => $translation['body'] !== null ? (string) $translation['body'] : null,
                'context' => $translation['context'] !== null ? (string) $translation['context'] : null,
                'status' => (string) $translation['status'],
                'updated_at' => (string) ($translation['updated_at'] ?? ''),
            ] : null,
        ]);
    }

    if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH') {
        if (!api_calendar_entity_exists($pdo, $entityType, $id)) {
            api_error(404, 'Calendar item not found');
        }
        $payload = api_read_json_body();
        $title = trim((string) ($payload['title'] ?? ''));
        $bodyRaw = $payload['body'] ?? null;
        $contextRaw = $payload['context'] ?? null;
        $body = is_string($bodyRaw) ? trim($bodyRaw) : null;
        $context = is_string($contextRaw) ? trim($contextRaw) : null;
        $status = api_normalize_status((string) ($payload['status'] ?? 'published'));

        if ($title === '') {
            api_error(400, 'Title is required');
        }

        if ($body !== null && $body !== '') {
            if ($entityType === 'history') {
                $body = trim(strip_tags(str_replace(["\r\n", "\r"], "\n", $body)));
            } else {
                $body = api_sanitize_html(normalize_article_body_html($body));
            }
            if ($body === '') {
                $body = null;
            }
        } else {
            $body = null;
        }

        calendar_translation_upsert(
            $pdo,
            $entityType,
            $id,
            $lang,
            $title,
            $body,
            $context !== '' ? $context : null,
            $status
        );
        api_json(200, ['ok' => true]);
    }

    api_error(405, 'Method not allowed');
}
