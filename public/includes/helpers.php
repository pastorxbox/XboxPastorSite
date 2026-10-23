<?php

declare(strict_types=1);

require_once __DIR__ . '/i18n.php';

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Ícone Lucide inline para a navegação principal.
 *
 * @param 'home'|'news'|'articles'|'calendar'|'search' $kind
 */
function nav_lucide_icon(string $kind, int $size = 18): string
{
    $icons = [
        'home' => [
            'paths' => '<path d="M15 21v-8a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v8"/><path d="M3 10a2 2 0 0 1 .709-1.528l7-5.999a2 2 0 0 1 2.582 0l7 5.999A2 2 0 0 1 21 10v9a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
        ],
        'news' => [
            'paths' => '<path d="M4 22h16a2 2 0 0 0 2-2V4a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v16a2 2 0 0 1-2 2Zm0 0a2 2 0 0 1-2-2v-9c0-1.1.9-2 2-2h2"/><path d="M18 14h-8"/><path d="M15 18h-5"/><path d="M10 6h8v4h-8V6Z"/>',
        ],
        'articles' => [
            'paths' => '<path d="M15 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7Z"/><path d="M14 2v4a2 2 0 0 0 2 2h4"/><path d="M10 9H8"/><path d="M16 13H8"/><path d="M16 17H8"/>',
        ],
        'calendar' => [
            'paths' => '<path d="M8 2v4"/><path d="M16 2v4"/><rect width="18" height="18" x="3" y="4" rx="2"/><path d="M3 10h18"/>',
        ],
        'search' => [
            'paths' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>',
        ],
    ];

    if (!isset($icons[$kind])) {
        return '';
    }

    $sizePx = max(14, $size);

    return '<svg class="lucide lucide--nav-' . e($kind)
        . '" xmlns="http://www.w3.org/2000/svg" width="' . $sizePx . '" height="' . $sizePx
        . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" '
        . 'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
        . $icons[$kind]['paths'] . '</svg>';
}

function site_post_url(array $config, int $id): string
{
    $base = rtrim((string) ($config['site_base_url'] ?? ''), '/');
    $params = ['p' => $id];
    if (current_lang() !== 'pt') {
        $params['lang'] = current_lang();
    }

    return $base . '/?' . http_build_query($params);
}

/**
 * Turn a relative/site path into an absolute URL for Open Graph and similar.
 */
function absolute_url(array $config, string $pathOrUrl): string
{
    $pathOrUrl = trim($pathOrUrl);
    if ($pathOrUrl === '') {
        return '';
    }

    if (preg_match('#^https?://#i', $pathOrUrl) === 1) {
        return $pathOrUrl;
    }

    $base = rtrim((string) ($config['site_base_url'] ?? ''), '/');
    if ($base === '') {
        return $pathOrUrl;
    }

    if (str_starts_with($pathOrUrl, '//')) {
        $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

        return $scheme . ':' . $pathOrUrl;
    }

    return $base . '/' . ltrim($pathOrUrl, '/');
}

function site_home_url(array $config, int $page = 1): string
{
    return site_list_url($config, $page, null, null);
}

function site_section_url(array $config, string $section, int $page = 1): string
{
    $normalized = strtolower($section);
    if ($normalized === 'calendario' || $normalized === 'calendar') {
        $public = 'calendario';
    } else {
        $public = ($normalized === 'article' || $section === 'artigos') ? 'artigos' : 'noticias';
    }

    return site_list_url($config, $page, $public, null);
}

function site_tag_url(array $config, string $slug, int $page = 1): string
{
    return site_list_url($config, $page, null, $slug);
}

function site_search_url(array $config, string $q = '', int $page = 1): string
{
    $base = rtrim((string) ($config['site_base_url'] ?? ''), '/') . '/busca';
    $params = [];
    $q = trim($q);
    if ($q !== '') {
        $params['q'] = $q;
    }
    if ($page > 1) {
        $params['paged'] = $page;
    }
    if (current_lang() !== 'pt') {
        $params['lang'] = current_lang();
    }

    if ($params === []) {
        return $base;
    }

    return $base . '?' . http_build_query($params);
}

/**
 * Escapa curingas LIKE (% e _) e envolve o termo com %.
 */
function search_like_pattern(string $q): string
{
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], trim($q));

    return '%' . $escaped . '%';
}

function site_list_url(array $config, int $page = 1, ?string $secao = null, ?string $tag = null): string
{
    $base = rtrim((string) ($config['site_base_url'] ?? ''), '/');
    if (is_string($tag) && $tag !== '') {
        $url = $base . '/tag/' . rawurlencode($tag);
    } elseif ($secao === 'noticias') {
        $url = $base . '/noticias';
    } elseif ($secao === 'artigos') {
        $url = $base . '/artigos';
    } elseif ($secao === 'calendario') {
        $url = $base . '/calendario';
    } else {
        $url = $base . '/';
    }

    $params = [];
    if ($page > 1) {
        $params['paged'] = $page;
    }
    if (current_lang() !== 'pt') {
        $params['lang'] = current_lang();
    }

    if ($params === []) {
        return $url;
    }

    $query = http_build_query($params);
    if ($url === $base . '/') {
        return $base . '/?' . $query;
    }

    return $url . '?' . $query;
}

/**
 * Números de página compactos para mobile (null = reticências).
 *
 * @return list<int|null>
 */
function pagination_items(int $current, int $total, int $window = 1): array
{
    $total = max(1, $total);
    $current = max(1, min($current, $total));

    if ($total <= 7) {
        return range(1, $total);
    }

    $wanted = [1 => true, $total => true];
    for ($i = $current - $window; $i <= $current + $window; ++$i) {
        if ($i >= 1 && $i <= $total) {
            $wanted[$i] = true;
        }
    }

    ksort($wanted, SORT_NUMERIC);
    $items = [];
    $prev = null;
    foreach (array_keys($wanted) as $n) {
        if ($prev !== null && $n > $prev + 1) {
            $items[] = null;
        }
        $items[] = $n;
        $prev = $n;
    }

    return $items;
}

/**
 * @param callable(int): string $urlForPage
 */
function render_pagination_nav(int $page, int $totalPages, callable $urlForPage): string
{
    if ($totalPages <= 1) {
        return '';
    }

    $items = pagination_items($page, $totalPages);
    ob_start();
    ?>
            <nav class="pagination" aria-label="<?= e(t('ui.pagination')) ?>">
                <?php if ($page > 1): ?>
                    <a class="pagination__arrow" href="<?= e($urlForPage($page - 1)) ?>" aria-label="<?= e(t('ui.prev_page')) ?>">‹</a>
                <?php else: ?>
                    <a class="pagination__arrow disabled" href="<?= e($urlForPage(1)) ?>" aria-disabled="true" aria-label="<?= e(t('ui.prev_page')) ?>">‹</a>
                <?php endif; ?>

                <?php foreach ($items as $item): ?>
                    <?php if ($item === null): ?>
                        <span class="pagination__ellipsis" aria-hidden="true">…</span>
                    <?php elseif ($item === $page): ?>
                        <span class="current" aria-current="page"><?= e((string) $item) ?></span>
                    <?php else: ?>
                        <a href="<?= e($urlForPage($item)) ?>"><?= e((string) $item) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($page < $totalPages): ?>
                    <a class="pagination__arrow" href="<?= e($urlForPage($page + 1)) ?>" aria-label="<?= e(t('ui.next_page')) ?>">›</a>
                <?php else: ?>
                    <a class="pagination__arrow disabled" href="<?= e($urlForPage($totalPages)) ?>" aria-disabled="true" aria-label="<?= e(t('ui.next_page')) ?>">›</a>
                <?php endif; ?>
            </nav>
    <?php

    return (string) ob_get_clean();
}

function format_post_date(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }

    return match (current_lang()) {
        'en' => date('m/d/Y H:i', $timestamp),
        default => date('d/m/Y H:i', $timestamp),
    };
}

function reading_time_label(int $minutes): string
{
    return t('ui.read_time', ['n' => max(1, $minutes)]);
}

/** Data de lançamento: omite 00:00 e deixa só o dia. */
function format_release_date(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }

    $hasTime = date('H:i', $timestamp) !== '00:00';

    return match (current_lang()) {
        'en' => $hasTime ? date('m/d/Y H:i', $timestamp) : date('m/d/Y', $timestamp),
        default => $hasTime ? date('d/m/Y H:i', $timestamp) : date('d/m/Y', $timestamp),
    };
}

/** Data curta para cards: 10/Set */
function format_release_arrive_date(?string $datetime): string
{
    if ($datetime === null || $datetime === '') {
        return '';
    }

    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return '';
    }

    $monthNum = (int) date('n', $timestamp);
    $month = t('cal.month_short.' . $monthNum);
    $day = date('d', $timestamp);

    return $day . '/' . $month;
}

function release_arrive_label(?string $datetime): string
{
    $date = format_release_arrive_date($datetime);
    if ($date === '') {
        return '';
    }

    return t('cal.arrives', ['date' => $date]);
}

function youtube_icon_svg(int $size = 18): string
{
    $sizePx = max(12, $size);

    return '<svg class="cal-card__yt-icon" xmlns="http://www.w3.org/2000/svg" width="'
        . $sizePx . '" height="' . $sizePx . '" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path fill="#FF0000" d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31.2 31.2 0 0 0 0 12a31.2 31.2 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31.2 31.2 0 0 0 24 12a31.2 31.2 0 0 0-.5-5.8z"/>'
        . '<path fill="#fff" d="M9.75 15.02V8.98L15.5 12l-5.75 3.02z"/>'
        . '</svg>';
}

function excerpt_from_html(string $html, int $maxLength = 160): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($html)) ?? '');
    if ($text === '') {
        return '';
    }

    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $maxLength - 1)) . '…';
    }

    if (strlen($text) <= $maxLength) {
        return $text;
    }

    return rtrim(substr($text, 0, $maxLength - 1)) . '…';
}

function reading_time_minutes(string $html): int
{
    $text = trim(strip_tags($html));
    if ($text === '') {
        return 1;
    }

    $words = preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
    $count = is_array($words) ? count($words) : 0;

    return max(1, (int) ceil($count / 200));
}

function first_image_src(string $html): ?string
{
    if (preg_match('/<img[^>]+src=["\']([^"\']+)["\']/i', $html, $matches) !== 1) {
        return null;
    }

    $src = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');

    return $src !== '' ? $src : null;
}

/**
 * Unescape leftover LLM/JSON artifacts and drop duplicate bullet glyphs before emoji markers.
 */
function normalize_article_body_html(string $html): string
{
    $body = trim($html);
    if ($body === '') {
        return '';
    }

    for ($i = 0; $i < 4; $i++) {
        $body = str_replace(
            ["\\r\\n", "\\n", "\\r", "\\t", '\\"', "\\'", '\\\\'],
            ["\n", "\n", '', "\t", '"', "'", '\\'],
            $body
        );
    }

    // "• ✅", "- ✅", etc. → keep only the emoji marker
    $body = preg_replace(
        '/(^|[\n>])\s*(?:[•·●◦▪▫‣⁃]|[-*–—])\s*((?:✅|☑️|✔|✓|❌|⚠️|🔥|🚨|➡️|👉)(?:\x{FE0F})?)/u',
        '$1$2',
        $body
    ) ?? $body;
    $body = preg_replace(
        '/(<li[^>]*>)\s*(?:[•·●◦▪▫‣⁃]|[-*–—])\s*((?:✅|☑️|✔|✓|❌|⚠️|🔥|🚨|➡️|👉)(?:\x{FE0F})?)/iu',
        '$1$2',
        $body
    ) ?? $body;

    if (!preg_match('/<(p|h[1-6]|ul|ol|div|figure|table)\b/i', $body)) {
        $chunks = preg_split("/\n{2,}/", $body) ?: [];
        $parts = [];
        foreach ($chunks as $chunk) {
            $chunk = trim((string) $chunk);
            if ($chunk === '') {
                continue;
            }
            $chunk = str_replace("\n", "<br>\n", $chunk);
            $parts[] = '<p>' . $chunk . '</p>';
        }
        if ($parts !== []) {
            return implode("\n", $parts);
        }
    }

    return $body;
}

/** ID (11 caracteres) de URLs youtu.be, watch?v=, shorts/, embed/ ou live/. */
function youtube_video_id(?string $url): ?string
{
    $url = trim((string) $url);
    if ($url === '') {
        return null;
    }
    if (preg_match('/^[A-Za-z0-9_-]{11}$/', $url) === 1) {
        return $url;
    }
    $patterns = [
        '#youtu\.be/([A-Za-z0-9_-]{11})#i',
        '#youtube(?:-nocookie)?\.com/(?:embed|shorts|live|v)/([A-Za-z0-9_-]{11})#i',
        '#youtube\.com/.*[?&]v=([A-Za-z0-9_-]{11})#i',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $url, $m) === 1) {
            return $m[1];
        }
    }

    return null;
}

function youtube_thumbnail_url(string $videoId): string
{
    return 'https://i.ytimg.com/vi/' . rawurlencode($videoId) . '/hqdefault.jpg';
}

/**
 * Embed mudo com autoplay. $start/$end em segundos (null = vídeo inteiro em loop).
 */
function youtube_muted_embed_url(string $videoId, ?int $start = null, ?int $end = null, bool $controls = false): string
{
    $params = [
        'autoplay' => '1',
        'mute' => '1',
        'playsinline' => '1',
        'controls' => $controls ? '1' : '0',
        'rel' => '0',
        'modestbranding' => '1',
        'iv_load_policy' => '3',
    ];
    if ($start !== null) {
        $params['start'] = (string) $start;
    }
    if ($end !== null) {
        $params['end'] = (string) $end;
    }
    if ($start === null && $end === null) {
        $params['loop'] = '1';
        $params['playlist'] = $videoId;
    }

    return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($videoId) . '?' . http_build_query($params);
}

/** Default social/OG + hero fallback when a post has no featured <img>. */
function default_share_image_path(): string
{
    return '/assets/img/og-share-default.png';
}

/**
 * @return array{src: ?string, alt: string, body: string}
 */
function extract_featured_image(string $html): array
{
    $src = null;
    $alt = '';
    $html = normalize_article_body_html($html);
    $body = $html;

    if (preg_match('#<figure class="article__featured">\s*<img([^>]*)>\s*</figure>#i', $html, $figure) === 1) {
        if (preg_match('/src=["\']([^"\']+)["\']/', $figure[1], $srcMatch) === 1) {
            $src = html_entity_decode($srcMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('/alt=["\']([^"\']*)["\']/', $figure[1], $altMatch) === 1) {
            $alt = html_entity_decode($altMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        $body = trim(str_replace($figure[0], '', $html));
    } elseif (preg_match(
        '#(?:<figure[^>]*>\s*)?<img([^>]*)>(?:\s*</figure>)?#i',
        $html,
        $imgBlock,
        PREG_OFFSET_CAPTURE
    ) === 1) {
        $attrs = $imgBlock[1][0];
        if (preg_match('/src=["\']([^"\']+)["\']/', $attrs, $srcMatch) === 1) {
            $src = html_entity_decode($srcMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
        if (preg_match('/alt=["\']([^"\']*)["\']/', $attrs, $altMatch) === 1) {
            $alt = html_entity_decode($altMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        // Also drop a wrapping <p> around the leading hero image when present.
        $start = $imgBlock[0][1];
        $length = strlen($imgBlock[0][0]);
        $before = substr($html, 0, $start);
        $after = substr($html, $start + $length);
        if (
            preg_match('#<p[^>]*>\s*$#i', $before) === 1
            && preg_match('#^\s*</p>#i', $after) === 1
        ) {
            $before = preg_replace('#<p[^>]*>\s*$#i', '', $before) ?? $before;
            $after = preg_replace('#^\s*</p>#i', '', $after) ?? $after;
        }
        $body = trim($before . $after);
    }

    return [
        'src' => $src !== '' ? $src : null,
        'alt' => $alt,
        'body' => $body,
    ];
}
