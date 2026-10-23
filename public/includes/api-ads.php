<?php

declare(strict_types=1);

function api_ads_response(string $type, int|string $id): never
{
    api_json(200, [
        'type' => $type,
        'id' => $id,
    ]);
}

/**
 * @return array{
 *   image_url: string,
 *   link_url: ?string,
 *   duration_sec: int,
 *   sort_order: int,
 *   status: string
 * }
 */
function api_parse_ad_slide_payload(array $payload): array
{
    $imageUrl = api_optional_string($payload['image_url'] ?? null, 500);
    if ($imageUrl === null) {
        api_error(400, 'image_url is required');
    }

    $duration = (int) ($payload['duration_sec'] ?? 5);
    if ($duration < 1) {
        api_error(400, 'duration_sec must be at least 1');
    }
    if ($duration > 600) {
        api_error(400, 'duration_sec too large');
    }

    $linkUrl = api_optional_string($payload['link_url'] ?? null, 500);
    if ($linkUrl !== null) {
        $scheme = strtolower((string) (parse_url($linkUrl, PHP_URL_SCHEME) ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            api_error(400, 'link_url must be http or https');
        }
    }

    return [
        'image_url' => $imageUrl,
        'link_url' => $linkUrl,
        'duration_sec' => $duration,
        'sort_order' => (int) ($payload['sort_order'] ?? 0),
        'status' => api_normalize_status((string) ($payload['status'] ?? 'published')),
    ];
}

function api_handle_ads_list(PDO $pdo, array $config): never
{
    api_require_method('GET');
    api_require_https($config);
    api_require_auth($config);
    api_json(200, ['slots' => ads_list_slots_admin($pdo)]);
}

function api_handle_ads_slot_patch(PDO $pdo, array $config, string $slug): never
{
    api_require_method('PATCH');
    api_require_https($config);
    api_require_auth($config);

    if (!in_array($slug, AD_SLOT_SLUGS, true) || ads_get_slot_by_slug($pdo, $slug) === null) {
        api_error(404, 'Ad slot not found');
    }

    $payload = api_read_json_body();
    $fields = [];

    if (array_key_exists('enabled', $payload)) {
        $fields['enabled'] = api_bool_flag($payload['enabled']) === 1;
    }

    if (array_key_exists('width_px', $payload)) {
        $width = (int) $payload['width_px'];
        if ($width < 40 || $width > 2400) {
            api_error(400, 'width_px must be between 40 and 2400');
        }
        $fields['width_px'] = $width;
    }

    if (array_key_exists('height_px', $payload)) {
        $height = (int) $payload['height_px'];
        if ($height < 40 || $height > 1200) {
            api_error(400, 'height_px must be between 40 and 1200');
        }
        $fields['height_px'] = $height;
    }

    if ($fields === []) {
        api_error(400, 'enabled, width_px or height_px is required');
    }

    ads_update_slot($pdo, $slug, $fields);
    api_ads_response('slot', $slug);
}

function api_handle_ads_slide_create(PDO $pdo, array $config, string $slug): never
{
    api_require_method('POST');
    api_require_https($config);
    api_require_auth($config);

    $slot = ads_get_slot_by_slug($pdo, $slug);
    if ($slot === null) {
        api_error(404, 'Ad slot not found');
    }

    $data = api_parse_ad_slide_payload(api_read_json_body());
    $id = ads_create_slide($pdo, (int) $slot['id'], $data);
    api_ads_response('slide', $id);
}

function api_handle_ads_slide_update(PDO $pdo, array $config, int $id): never
{
    api_require_method('PATCH');
    api_require_https($config);
    api_require_auth($config);

    if ($id <= 0 || ads_get_slide($pdo, $id) === null) {
        api_error(404, 'Ad slide not found');
    }

    $data = api_parse_ad_slide_payload(api_read_json_body());
    ads_update_slide($pdo, $id, $data);
    api_ads_response('slide', $id);
}

function api_handle_ads_slide_delete(PDO $pdo, array $config, int $id): never
{
    api_require_method('DELETE');
    api_require_https($config);
    api_require_auth($config);

    if ($id <= 0 || ads_get_slide($pdo, $id) === null) {
        api_error(404, 'Ad slide not found');
    }

    ads_delete_slide($pdo, $id);
    api_ads_response('slide', $id);
}
