<?php

declare(strict_types=1);

function api_handle_push_vapid_public(array $config): never
{
    api_require_method('GET');
    api_require_https($config);

    $publicKey = push_vapid_public_key($config);
    if ($publicKey === '') {
        api_error(503, 'Push not configured');
    }

    api_json(200, ['publicKey' => $publicKey]);
}

function api_handle_push_subscribe(PDO $pdo, array $config): never
{
    api_require_https($config);
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'POST') {
        if (!push_is_configured($config)) {
            api_error(503, 'Push not configured');
        }

        $payload = api_read_json_body();
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        $keys = is_array($payload['keys'] ?? null) ? $payload['keys'] : [];
        $subscription = [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => trim((string) ($keys['p256dh'] ?? '')),
                'auth' => trim((string) ($keys['auth'] ?? '')),
            ],
            'contentEncoding' => trim((string) ($payload['contentEncoding'] ?? 'aesgcm')),
        ];

        try {
            push_save_subscription(
                $pdo,
                $subscription,
                isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null
            );
        } catch (InvalidArgumentException $e) {
            api_error(400, $e->getMessage());
        } catch (Throwable) {
            api_error(500, 'Failed to save subscription');
        }

        api_json(200, ['ok' => true]);
    }

    if ($method === 'DELETE') {
        $payload = api_read_json_body();
        $endpoint = trim((string) ($payload['endpoint'] ?? ''));
        if ($endpoint === '') {
            api_error(400, 'endpoint is required');
        }
        push_delete_subscription($pdo, $endpoint);
        api_json(200, ['ok' => true]);
    }

    api_error(405, 'Method not allowed');
}
