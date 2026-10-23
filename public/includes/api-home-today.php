<?php

declare(strict_types=1);

require_once __DIR__ . '/home-today.php';

function api_handle_home_today_settings(PDO $pdo, array $config): never
{
    api_require_https($config);
    api_require_auth($config);

    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        api_json(200, [
            'slide' => home_today_slide_get($pdo),
            'today' => home_today_date(),
        ]);
    }

    if ($method === 'PUT' || $method === 'POST' || $method === 'PATCH') {
        $payload = api_read_json_body();

        $date = trim((string) ($payload['date'] ?? ''));
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            api_error(400, 'date deve ser YYYY-MM-DD');
        }
        $image = trim((string) ($payload['image_url'] ?? ''));
        if ($image === '' || (!str_starts_with($image, '/uploads/') && preg_match('#^https://#i', $image) !== 1)) {
            api_error(400, 'image_url inválida');
        }
        $titles = [];
        foreach ((array) ($payload['titles'] ?? []) as $title) {
            $title = mb_substr(trim((string) $title), 0, 160);
            if ($title !== '') {
                $titles[] = $title;
            }
        }

        site_settings_put($pdo, HOME_TODAY_SLIDE_KEY, [
            'date' => $date,
            'image_url' => mb_substr($image, 0, 500),
            'target' => ($payload['target'] ?? '') === 'gamepass' ? 'gamepass' : 'xbox',
            'titles' => array_slice($titles, 0, 20),
            'signature' => mb_substr(trim((string) ($payload['signature'] ?? '')), 0, 500),
            'updated_at' => gmdate('c'),
        ]);

        api_json(200, ['slide' => home_today_slide_get($pdo)]);
    }

    if ($method === 'DELETE') {
        site_settings_put($pdo, HOME_TODAY_SLIDE_KEY, []);
        api_json(200, ['slide' => null]);
    }

    api_error(405, 'Method not allowed');
}
