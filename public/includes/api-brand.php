<?php

declare(strict_types=1);

function api_handle_brand_settings(PDO $pdo, array $config): never
{
    api_require_https($config);
    api_require_auth($config);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $settings = site_settings_get($pdo, SITE_BRAND_SETTING_KEY, []);
        $logoUrl = trim((string) ($settings['logo_url'] ?? ''));
        api_json(200, [
            'logo_url' => $logoUrl !== '' ? $logoUrl : null,
            'resolved_url' => brand_logo_url($pdo),
        ]);
    }

    if ($method === 'POST') {
        api_validate_content_length();
        api_require_multipart_content_type();

        if (!isset($_FILES['file'])) {
            api_error(400, 'Missing file field');
        }

        $uploadsRoot = dirname(__DIR__) . '/uploads';
        if (!is_dir($uploadsRoot) && !mkdir($uploadsRoot, 0755, true) && !is_dir($uploadsRoot)) {
            api_error(500, 'Upload directory unavailable');
        }

        $url = api_store_uploaded_media($_FILES['file'], $uploadsRoot);
        $version = gmdate('YmdHis');
        site_settings_put($pdo, SITE_BRAND_SETTING_KEY, [
            'logo_url' => $url,
            'logo_version' => $version,
        ]);

        api_json(200, [
            'logo_url' => $url,
            'resolved_url' => brand_logo_url($pdo),
        ]);
    }

    if ($method === 'PUT' || $method === 'PATCH') {
        $payload = api_read_json_body();
        $clear = ($payload['clear'] ?? false) === true
            || array_key_exists('logo_url', $payload) && $payload['logo_url'] === null;

        if (!$clear) {
            api_error(400, 'Use clear:true or logo_url:null para restaurar o avatar padrão.');
        }

        site_settings_put($pdo, SITE_BRAND_SETTING_KEY, [
            'logo_url' => null,
            'logo_version' => gmdate('YmdHis'),
        ]);

        api_json(200, [
            'logo_url' => null,
            'resolved_url' => brand_logo_url($pdo),
        ]);
    }

    api_error(405, 'Method not allowed');
}
