<?php

declare(strict_types=1);

/**
 * Web Push helpers (VAPID + subscriptions).
 */

function push_vapid_public_key(array $config): string
{
    return trim((string) ($config['vapid_public_key'] ?? ''));
}

function push_vapid_private_key(array $config): string
{
    return trim((string) ($config['vapid_private_key'] ?? ''));
}

function push_vapid_subject(array $config): string
{
    $subject = trim((string) ($config['vapid_subject'] ?? ''));
    if ($subject !== '') {
        return $subject;
    }

    return 'mailto:admin@pastorxbox.com.br';
}

function push_is_configured(array $config): bool
{
    return push_vapid_public_key($config) !== '' && push_vapid_private_key($config) !== '';
}

/**
 * @param array{endpoint: string, keys: array{p256dh: string, auth: string}, contentEncoding?: string} $subscription
 */
function push_save_subscription(PDO $pdo, array $subscription, ?string $userAgent = null): void
{
    $endpoint = trim((string) ($subscription['endpoint'] ?? ''));
    $p256dh = trim((string) ($subscription['keys']['p256dh'] ?? ''));
    $auth = trim((string) ($subscription['keys']['auth'] ?? ''));
    $encoding = trim((string) ($subscription['contentEncoding'] ?? 'aesgcm'));
    if ($encoding === '') {
        $encoding = 'aesgcm';
    }

    if ($endpoint === '' || $p256dh === '' || $auth === '') {
        throw new InvalidArgumentException('Invalid push subscription');
    }
    if (strlen($endpoint) > 500) {
        throw new InvalidArgumentException('Endpoint too long');
    }

    $stmt = $pdo->prepare(
        'INSERT INTO push_subscriptions (endpoint, p256dh, auth, content_encoding, user_agent)
         VALUES (:endpoint, :p256dh, :auth, :content_encoding, :user_agent)
         ON DUPLICATE KEY UPDATE
            p256dh = VALUES(p256dh),
            auth = VALUES(auth),
            content_encoding = VALUES(content_encoding),
            user_agent = VALUES(user_agent),
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        'endpoint' => $endpoint,
        'p256dh' => $p256dh,
        'auth' => $auth,
        'content_encoding' => $encoding,
        'user_agent' => $userAgent !== null && $userAgent !== '' ? substr($userAgent, 0, 500) : null,
    ]);
}

function push_delete_subscription(PDO $pdo, string $endpoint): void
{
    $endpoint = trim($endpoint);
    if ($endpoint === '') {
        return;
    }
    $stmt = $pdo->prepare('DELETE FROM push_subscriptions WHERE endpoint = :endpoint');
    $stmt->execute(['endpoint' => $endpoint]);
}

/**
 * @return list<array<string, mixed>>
 */
function push_list_subscriptions(PDO $pdo): array
{
    return $pdo->query(
        'SELECT id, endpoint, p256dh, auth, content_encoding
         FROM push_subscriptions
         ORDER BY id ASC'
    )->fetchAll();
}

/**
 * @param array{title: string, body: string, url: string, icon?: string} $payload
 */
function push_send_to_all(PDO $pdo, array $config, array $payload): int
{
    if (!push_is_configured($config)) {
        return 0;
    }
    if (!class_exists(\Minishlink\WebPush\WebPush::class)) {
        return 0;
    }

    $auth = [
        'VAPID' => [
            'subject' => push_vapid_subject($config),
            'publicKey' => push_vapid_public_key($config),
            'privateKey' => push_vapid_private_key($config),
        ],
    ];

    $webPush = new \Minishlink\WebPush\WebPush($auth);
    $webPush->setReuseVAPIDHeaders(true);

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($json)) {
        return 0;
    }

    $sent = 0;
    foreach (push_list_subscriptions($pdo) as $row) {
        $subscription = \Minishlink\WebPush\Subscription::create([
            'endpoint' => (string) $row['endpoint'],
            'publicKey' => (string) $row['p256dh'],
            'authToken' => (string) $row['auth'],
            'contentEncoding' => (string) ($row['content_encoding'] ?: 'aesgcm'),
        ]);
        $webPush->queueNotification($subscription, $json);
        $sent++;
    }

    foreach ($webPush->flush() as $report) {
        if (!$report->isSuccess()) {
            $code = $report->getResponse() ? $report->getResponse()->getStatusCode() : 0;
            if (in_array($code, [404, 410], true)) {
                push_delete_subscription($pdo, $report->getEndpoint());
            }
        }
    }

    return $sent;
}

function push_notify_new_post(PDO $pdo, array $config, int $postId): void
{
    if ($postId <= 0 || !push_is_configured($config)) {
        return;
    }

    $post = posts_get_by_id($pdo, $postId);
    if ($post === null || (string) ($post['status'] ?? '') !== 'published') {
        return;
    }

    $title = (string) ($post['title'] ?? 'Pastor Xbox');
    $bodyHtml = (string) ($post['body'] ?? '');
    $excerpt = function_exists('excerpt_from_html')
        ? excerpt_from_html($bodyHtml, 120)
        : '';
    $url = site_post_url($config, $postId);
    $icon = rtrim((string) ($config['site_base_url'] ?? ''), '/') . '/assets/img/pwa-icon-192.png';

    push_send_to_all($pdo, $config, [
        'title' => $title,
        'body' => $excerpt !== '' ? $excerpt : 'Nova notícia no Pastor Xbox',
        'url' => $url,
        'icon' => $icon,
    ]);
}
