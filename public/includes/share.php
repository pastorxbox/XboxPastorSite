<?php

declare(strict_types=1);

/**
 * @return list<array{id: string, label_key: string, mode: 'intent'|'clipboard', limit: int}>
 */
function share_networks(): array
{
    return [
        ['id' => 'x', 'label_key' => 'share.network.x', 'mode' => 'intent', 'limit' => 280],
        ['id' => 'instagram', 'label_key' => 'share.network.instagram', 'mode' => 'clipboard', 'limit' => 1000],
        ['id' => 'bluesky', 'label_key' => 'share.network.bluesky', 'mode' => 'intent', 'limit' => 300],
        ['id' => 'whatsapp', 'label_key' => 'share.network.whatsapp', 'mode' => 'intent', 'limit' => 700],
    ];
}

function share_str_len(string $text): int
{
    return function_exists('mb_strlen') ? mb_strlen($text) : strlen($text);
}

function share_str_cut(string $text, int $maxLength): string
{
    if ($maxLength <= 0) {
        return '';
    }
    if (share_str_len($text) <= $maxLength) {
        return $text;
    }
    if (function_exists('mb_substr')) {
        return rtrim(mb_substr($text, 0, max(1, $maxLength - 1))) . '…';
    }

    return rtrim(substr($text, 0, max(1, $maxLength - 1))) . '…';
}

/**
 * @return array{id: string, label_key: string, mode: 'intent'|'clipboard', limit: int}|null
 */
function share_network_meta(string $network): ?array
{
    foreach (share_networks() as $item) {
        if ($item['id'] === $network) {
            return $item;
        }
    }

    return null;
}

function share_compose_message(string $title, string $bodyHtml, string $url, string $network): string
{
    $meta = share_network_meta($network);
    $limit = $meta['limit'] ?? 280;
    $title = trim($title);
    $url = trim($url);
    $seeMore = t('share.see_more');
    $footer = $seeMore . "\n" . $url;

    $fixed = $title . "\n\n" . $footer;
    $fixedLen = share_str_len($fixed);
    // Room for blank line before footer when excerpt exists: "\n\n"
    $excerptBudget = $limit - $fixedLen - 2;
    $excerpt = '';
    if ($excerptBudget >= 12) {
        $excerpt = excerpt_from_html($bodyHtml, $excerptBudget);
    }

    if ($excerpt !== '') {
        $message = $title . "\n\n" . $excerpt . "\n\n" . $footer;
    } else {
        $message = $title . "\n\n" . $footer;
    }

    if (share_str_len($message) > $limit) {
        $message = share_str_cut($message, $limit);
    }

    return $message;
}

function share_intent_url(string $network, string $text): ?string
{
    $encoded = rawurlencode($text);

    return match ($network) {
        'x' => 'https://twitter.com/intent/tweet?text=' . $encoded,
        'bluesky' => 'https://bsky.app/intent/compose?text=' . $encoded,
        'whatsapp' => 'https://api.whatsapp.com/send?text=' . $encoded,
        default => null,
    };
}

function share_network_icon_svg(string $network, int $size = 16): string
{
    $sizePx = max(12, $size);
    $common = 'xmlns="http://www.w3.org/2000/svg" width="' . $sizePx . '" height="' . $sizePx
        . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false"';

    return match ($network) {
        'x' => '<svg class="share-menu__icon" ' . $common . ' fill="currentColor">'
            . '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.727-8.924L1.254 2.25H8.08l4.253 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z"/>'
            . '</svg>',
        'instagram' => '<svg class="share-menu__icon" ' . $common . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<rect x="2" y="2" width="20" height="20" rx="5"/><path d="M16 11.37A4 4 0 1 1 12.63 8 4 4 0 0 1 16 11.37z"/><line x1="17.5" y1="6.5" x2="17.51" y2="6.5"/>'
            . '</svg>',
        'bluesky' => '<svg class="share-menu__icon" ' . $common . ' fill="currentColor">'
            . '<path d="M12 11.5c-1.8-3.5-6.6-10-8.7-10C1.7 1.5 1 3.4 1 5.5 1 10 6.2 16.2 12 21c5.8-4.8 11-11 11-15.5 0-2.1-.7-4-2.3-4-2.1 0-6.9 6.5-8.7 10z"/>'
            . '</svg>',
        'whatsapp' => '<svg class="share-menu__icon" ' . $common . ' fill="currentColor">'
            . '<path d="M17.5 14.4c-.3-.1-1.6-.8-1.9-.9-.3-.1-.5-.1-.7.1-.2.3-.7.9-.9 1.1-.2.2-.3.2-.6.1-1.6-.7-3-1.9-4.1-3.4-.2-.3 0-.4.2-.6.2-.2.3-.3.5-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5 0-.1-.7-1.7-.9-2.3-.3-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1 2.8 1.2 3c.2.2 2 3.1 4.9 4.3 1.8.7 2.5.8 3.4.7.5-.1 1.6-.7 1.8-1.3.2-.6.2-1.2.2-1.3 0-.1-.2-.2-.5-.3z"/>'
            . '<path d="M12 2a10 10 0 0 0-8.7 14.9L2 22l5.2-1.4A10 10 0 1 0 12 2zm0 18.2c-1.5 0-3-.4-4.3-1.2l-.3-.2-3.1.8.8-3-.2-.3A8.2 8.2 0 1 1 12 20.2z"/>'
            . '</svg>',
        default => '<svg class="share-menu__icon" ' . $common . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
            . '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/>'
            . '<path d="m8.6 13.5 6.8 3.9M15.4 6.6l-6.8 3.9"/>'
            . '</svg>',
    };
}

/**
 * @param array<string, mixed> $config
 * @param array<string, mixed> $post
 */
function render_share_menu(array $config, array $post): string
{
    $postId = (int) ($post['id'] ?? 0);
    if ($postId <= 0) {
        return '';
    }

    $title = (string) ($post['title'] ?? '');
    $body = (string) ($post['body'] ?? '');
    $url = site_post_url($config, $postId);
    $networks = [];

    foreach (share_networks() as $network) {
        $id = $network['id'];
        $text = share_compose_message($title, $body, $url, $id);
        $networks[] = [
            'id' => $id,
            'label' => t($network['label_key']),
            'mode' => $network['mode'],
            'text' => $text,
            'href' => $network['mode'] === 'intent' ? share_intent_url($id, $text) : null,
        ];
    }

    $payload = json_encode(
        ['networks' => $networks, 'copied' => t('share.copied')],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    if (!is_string($payload)) {
        $payload = '{"networks":[],"copied":"Copied"}';
    }

    ob_start();
    ?>
                <div class="share-menu" data-share-menu data-share="<?= e($payload) ?>">
                    <button
                        type="button"
                        class="share-menu__toggle"
                        aria-expanded="false"
                        aria-haspopup="true"
                        aria-controls="share-menu-panel"
                        aria-label="<?= e(t('share.label')) ?>"
                    >
                        <?= share_network_icon_svg('share', 16) ?>
                        <span class="share-menu__toggle-label"><?= e(t('share.label')) ?></span>
                    </button>
                    <div class="share-menu__panel" id="share-menu-panel" role="menu" hidden>
                        <?php foreach ($networks as $network): ?>
                            <button
                                type="button"
                                class="share-menu__option"
                                role="menuitem"
                                data-share-network="<?= e((string) $network['id']) ?>"
                            >
                                <?= share_network_icon_svg((string) $network['id'], 16) ?>
                                <span><?= e((string) $network['label']) ?></span>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
    <?php

    return (string) ob_get_clean();
}
