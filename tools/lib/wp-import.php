<?php

declare(strict_types=1);

function wp_import_log(string $message): void
{
    fwrite(STDOUT, $message . PHP_EOL);
}

function wp_import_fail(string $message, int $code = 1): never
{
    fwrite(STDERR, $message . PHP_EOL);
    exit($code);
}

function wp_import_http_get(string $url): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 120,
            'header' => "User-Agent: PastorXboxImporter/1.0\r\nAccept: application/json\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $body = @file_get_contents($url, false, $context);
    if ($body === false) {
        wp_import_fail("Falha ao obter: {$url}");
    }

    return $body;
}

/**
 * @return array{total: int, total_pages: int}
 */
function wp_import_fetch_meta(string $restBaseUrl, int $perPage = 100): array
{
    $url = rtrim($restBaseUrl, '/') . '/posts?per_page=' . $perPage . '&status=publish&_embed';
    $context = stream_context_create([
        'http' => [
            'method' => 'HEAD',
            'timeout' => 60,
            'header' => "User-Agent: PastorXboxImporter/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $headers = @get_headers($url, true, $context);
    if ($headers === false) {
        wp_import_fail('Não foi possível contactar a API WordPress.');
    }

    $flat = [];
    foreach ($headers as $key => $value) {
        if (is_int($key)) {
            if (is_string($value) && str_contains($value, ':')) {
                [$name, $headerValue] = explode(':', $value, 2);
                $flat[strtolower(trim($name))] = trim($headerValue);
            }
            continue;
        }
        $flat[strtolower((string) $key)] = is_array($value) ? trim((string) end($value)) : trim((string) $value);
    }

    return [
        'total' => (int) ($flat['x-wp-total'] ?? 0),
        'total_pages' => max(1, (int) ($flat['x-wp-totalpages'] ?? 1)),
        'per_page' => $perPage,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function wp_import_fetch_posts_page(string $restBaseUrl, int $page, int $perPage = 100): array
{
    $query = http_build_query([
        'per_page' => $perPage,
        'page' => $page,
        'status' => 'publish',
        '_embed' => '1',
    ]);
    $url = rtrim($restBaseUrl, '/') . '/posts?' . $query;
    $json = wp_import_http_get($url);
    $posts = json_decode($json, true);
    if (!is_array($posts)) {
        wp_import_fail("Resposta inválida na página {$page}.");
    }

    return $posts;
}

function wp_import_normalize_title(string $renderedTitle): string
{
    $title = html_entity_decode(strip_tags($renderedTitle), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $title = trim(preg_replace('/\s+/u', ' ', $title) ?? '');

    return mb_substr($title, 0, 500);
}

function wp_import_parse_date(string $isoDate): ?string
{
    $timestamp = strtotime($isoDate);
    if ($timestamp === false) {
        return null;
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function wp_import_site_host(string $restBaseUrl): string
{
    $host = parse_url($restBaseUrl, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return 'pastorxbox.com.br';
    }

    return strtolower($host);
}

function wp_import_is_upload_url(string $url, string $siteHost): bool
{
    if ($url === '' || str_starts_with($url, 'data:')) {
        return false;
    }

    $parts = parse_url($url);
    if ($parts === false) {
        return false;
    }

    $path = (string) ($parts['path'] ?? '');
    if (!str_contains($path, '/wp-content/uploads/')) {
        return false;
    }

    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '') {
        return true;
    }

    $allowedHosts = [$siteHost, 'www.' . $siteHost];
    if (str_starts_with($siteHost, 'www.')) {
        $allowedHosts[] = substr($siteHost, 4);
    }

    return in_array($host, $allowedHosts, true);
}

function wp_import_upload_relative_path(string $url): ?string
{
    $path = parse_url($url, PHP_URL_PATH);
    if (!is_string($path) || !preg_match('#/wp-content/uploads/(.+)$#i', $path, $matches)) {
        return null;
    }

    $relative = rawurldecode($matches[1]);
    $relative = str_replace('\\', '/', $relative);
    if (str_contains($relative, '..')) {
        return null;
    }

    return '/uploads/' . $relative;
}

function wp_import_local_file_path(string $uploadsRoot, string $relativeUploadPath): string
{
    $relative = ltrim($relativeUploadPath, '/');
    if (str_starts_with($relative, 'uploads/')) {
        $relative = substr($relative, strlen('uploads/'));
    }

    return rtrim($uploadsRoot, '/') . '/' . $relative;
}

function wp_import_download_upload(string $remoteUrl, string $uploadsRoot, string $siteHost): ?string
{
    if (!wp_import_is_upload_url($remoteUrl, $siteHost)) {
        return null;
    }

    $relativePath = wp_import_upload_relative_path($remoteUrl);
    if ($relativePath === null) {
        return null;
    }

    $localPath = wp_import_local_file_path($uploadsRoot, $relativePath);
    $localDir = dirname($localPath);
    if (!is_dir($localDir) && !mkdir($localDir, 0755, true) && !is_dir($localDir)) {
        wp_import_fail("Não foi possível criar diretório: {$localDir}");
    }

    if (is_file($localPath) && filesize($localPath) > 0) {
        return $relativePath;
    }

    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 120,
            'header' => "User-Agent: PastorXboxImporter/1.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);

    $binary = @file_get_contents($remoteUrl, false, $context);
    if ($binary === false || $binary === '') {
        wp_import_fail("Falha ao descarregar imagem: {$remoteUrl}");
    }

    if (@file_put_contents($localPath, $binary) === false) {
        wp_import_fail("Falha ao gravar imagem: {$localPath}");
    }

    return $relativePath;
}

function wp_import_replace_upload_url(string $remoteUrl, string $uploadsRoot, string $siteHost): string
{
    $local = wp_import_download_upload($remoteUrl, $uploadsRoot, $siteHost);

    return $local ?? $remoteUrl;
}

function wp_import_process_srcset(string $srcset, string $uploadsRoot, string $siteHost): string
{
    $parts = array_map('trim', explode(',', $srcset));
    $rewritten = [];

    foreach ($parts as $part) {
        if ($part === '') {
            continue;
        }

        if (preg_match('/^(\S+)(\s+.+)?$/', $part, $matches) !== 1) {
            $rewritten[] = $part;
            continue;
        }

        $url = html_entity_decode($matches[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $descriptor = $matches[2] ?? '';
        $rewritten[] = wp_import_replace_upload_url($url, $uploadsRoot, $siteHost) . $descriptor;
    }

    return implode(', ', $rewritten);
}

function wp_import_process_body_images(string $html, string $uploadsRoot, string $siteHost): string
{
    $html = preg_replace_callback(
        '/\bsrcset=(["\'])([^"\']+)\1/i',
        function (array $matches) use ($uploadsRoot, $siteHost): string {
            $quote = $matches[1];
            $srcset = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return 'srcset=' . $quote . wp_import_process_srcset($srcset, $uploadsRoot, $siteHost) . $quote;
        },
        $html
    ) ?? $html;

    return preg_replace_callback(
        '/\bsrc=(["\'])([^"\']+)\1/i',
        function (array $matches) use ($uploadsRoot, $siteHost): string {
            $quote = $matches[1];
            $src = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');

            return 'src=' . $quote . wp_import_replace_upload_url($src, $uploadsRoot, $siteHost) . $quote;
        },
        $html
    ) ?? $html;
}

function wp_import_featured_media(array $post): ?array
{
    $embedded = $post['_embedded']['wp:featuredmedia'][0] ?? null;
    if (!is_array($embedded)) {
        return null;
    }

    $sourceUrl = (string) ($embedded['source_url'] ?? '');
    if ($sourceUrl === '') {
        return null;
    }

    return [
        'source_url' => $sourceUrl,
        'alt' => (string) ($embedded['alt_text'] ?? ''),
    ];
}

function wp_import_prepend_featured(string $body, ?array $featured, string $uploadsRoot, string $siteHost): string
{
    if ($featured === null) {
        return $body;
    }

    $sourceUrl = $featured['source_url'];
    if (str_contains($body, $sourceUrl) || str_contains($body, basename(parse_url($sourceUrl, PHP_URL_PATH) ?: ''))) {
        return $body;
    }

    $localPath = wp_import_download_upload($sourceUrl, $uploadsRoot, $siteHost);
    if ($localPath === null) {
        return $body;
    }

    $alt = htmlspecialchars($featured['alt'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $figure = '<figure class="article__featured"><img src="' . $localPath . '" alt="' . $alt . '" loading="lazy"></figure>';

    return $figure . "\n" . $body;
}

function wp_import_upsert_post(PDO $pdo, array $post, string $uploadsRoot, string $siteHost): void
{
    $id = (int) ($post['id'] ?? 0);
    if ($id <= 0) {
        return;
    }

    $title = wp_import_normalize_title((string) ($post['title']['rendered'] ?? ''));
    $body = (string) ($post['content']['rendered'] ?? '');
    $body = wp_import_process_body_images($body, $uploadsRoot, $siteHost);
    $body = wp_import_prepend_featured($body, wp_import_featured_media($post), $uploadsRoot, $siteHost);
    $publishedAt = wp_import_parse_date((string) ($post['date'] ?? ''));

    $stmt = $pdo->prepare(
        'INSERT INTO posts (id, title, body, status, published_at)
         VALUES (:id, :title, :body, :status, :published_at)
         ON DUPLICATE KEY UPDATE
            title = VALUES(title),
            body = VALUES(body),
            status = VALUES(status),
            published_at = VALUES(published_at)'
    );
    $stmt->execute([
        'id' => $id,
        'title' => $title,
        'body' => $body,
        'status' => 'published',
        'published_at' => $publishedAt,
    ]);
}

function wp_import_fix_auto_increment(PDO $pdo): void
{
    $pdo->exec('ALTER TABLE posts MODIFY id INT UNSIGNED NOT NULL AUTO_INCREMENT');
    $maxId = (int) $pdo->query('SELECT COALESCE(MAX(id), 0) FROM posts')->fetchColumn();
    $next = $maxId + 1;
    $pdo->exec('ALTER TABLE posts AUTO_INCREMENT = ' . $next);
}
