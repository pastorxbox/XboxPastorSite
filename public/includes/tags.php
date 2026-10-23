<?php

declare(strict_types=1);

const POST_SECTIONS = ['news', 'article'];

/** Palavras-chave → hashtag temática (iguais às das redes). Chaves longas primeiro. */
const TAG_KEYWORD_MAP = [
    'star wars' => 'StarWars',
    'xbox game pass' => 'GamePass',
    'game pass' => 'GamePass',
    'gamepass' => 'GamePass',
    'xbox cloud' => 'XboxCloud',
    'cloud gaming' => 'XboxCloud',
    'nintendo switch' => 'NintendoSwitch',
    'xbox controller' => 'Xbox',
    'diablo iv' => 'DiabloIV',
    'diablo 4' => 'DiabloIV',
    'switch 2' => 'NintendoSwitch',
    'playstation' => 'PlayStation',
    'microsoft' => 'Microsoft',
    'blizzard' => 'Blizzard',
    'insomniac' => 'Insomniac',
    'wolverine' => 'Wolverine',
    'diablo' => 'Diablo',
    'sony' => 'Sony',
    'xbox' => 'Xbox',
    'ps5' => 'PS5',
    'switch' => 'NintendoSwitch',
];

function section_normalize(?string $value): string
{
    $value = strtolower(trim((string) $value));
    if ($value === 'noticias' || $value === 'notícias' || $value === 'news') {
        return 'news';
    }
    if ($value === 'artigos' || $value === 'artigo' || $value === 'article' || $value === 'analises' || $value === 'análises') {
        return 'article';
    }

    return 'news';
}

function section_label(string $section): string
{
    $section = section_normalize($section);
    if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
        return $section === 'article' ? 'Artigos' : 'Notícias';
    }

    return $section === 'article'
        ? i18n_t($GLOBALS['pdo'], 'site.articles')
        : i18n_t($GLOBALS['pdo'], 'site.news');
}

function section_lead(string $section): string
{
    $section = section_normalize($section);
    if (!isset($GLOBALS['pdo']) || !$GLOBALS['pdo'] instanceof PDO) {
        return $section === 'article'
            ? 'Análises, opinião e textos mais longos do Pastor Xbox.'
            : 'Cobertura do dia a dia do Xbox, Game Pass e da indústria.';
    }

    return $section === 'article'
        ? i18n_t($GLOBALS['pdo'], 'lead.article')
        : i18n_t($GLOBALS['pdo'], 'lead.news');
}

function section_public_slug(string $section): string
{
    return section_normalize($section) === 'article' ? 'artigos' : 'noticias';
}

function tag_slugify(string $raw): string
{
    $value = trim($raw);
    $value = preg_replace('/^#+/', '', $value) ?? $value;
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value) ?: $value;
    $value = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '', $value));

    return substr($value, 0, 80);
}

function tag_display_name(string $raw): string
{
    $value = trim($raw);
    $value = preg_replace('/^#+/', '', $value) ?? $value;
    if (str_contains($value, ' ') || str_contains($value, ',')) {
        $value = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $value) ?? $value;
        $parts = preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $value = implode('', array_map(static function (string $word): string {
            if (function_exists('mb_substr') && function_exists('mb_strtoupper') && function_exists('mb_strtolower')) {
                return mb_strtoupper(mb_substr($word, 0, 1)) . mb_strtolower(mb_substr($word, 1));
            }

            return ucfirst(strtolower($word));
        }, $parts));
    }

    $value = preg_replace('/^#+/', '', $value) ?? $value;
    $value = preg_replace('/\s+/', '', $value) ?? $value;

    return substr($value, 0, 80);
}

/**
 * @param list<string> $rawTags
 * @return list<array{slug: string, name: string}>
 */
function tags_normalize_list(array $rawTags): array
{
    $seen = [];
    $out = [];
    foreach ($rawTags as $raw) {
        if (!is_string($raw) || trim($raw) === '') {
            continue;
        }
        $name = tag_display_name($raw);
        $slug = tag_slugify($name !== '' ? $name : $raw);
        if ($slug === '' || isset($seen[$slug])) {
            continue;
        }
        $seen[$slug] = true;
        $out[] = ['slug' => $slug, 'name' => $name !== '' ? $name : $slug];
    }

    return $out;
}

/**
 * Infere hashtags no mesmo espírito das redes (#Xbox + tema + #Gaming).
 *
 * @return list<string>
 */
function tags_infer_from_text(string $title, string $html): array
{
    $haystack = $title . "\n" . strip_tags($html);
    $lower = function_exists('mb_strtolower') ? mb_strtolower($haystack) : strtolower($haystack);

    $raw = ['Xbox', 'Gaming'];
    $keywords = TAG_KEYWORD_MAP;
    uksort($keywords, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

    foreach ($keywords as $needle => $tagName) {
        if (str_contains($lower, $needle)) {
            $raw[] = $tagName;
        }
    }

    return array_values(array_unique($raw));
}

function tags_ensure(PDO $pdo, string $slug, string $name): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO tags (slug, name) VALUES (:slug, :name)
         ON DUPLICATE KEY UPDATE name = VALUES(name)'
    );
    $stmt->execute(['slug' => $slug, 'name' => $name]);

    $idStmt = $pdo->prepare('SELECT id FROM tags WHERE slug = :slug LIMIT 1');
    $idStmt->execute(['slug' => $slug]);

    return (int) $idStmt->fetchColumn();
}

/**
 * @param list<string> $rawTags
 */
function tags_sync_for_post(PDO $pdo, int $postId, array $rawTags): void
{
    $normalized = tags_normalize_list($rawTags);
    $pdo->prepare('DELETE FROM post_tags WHERE post_id = :post_id')->execute(['post_id' => $postId]);

    $link = $pdo->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)');
    foreach ($normalized as $tag) {
        $tagId = tags_ensure($pdo, $tag['slug'], $tag['name']);
        $link->execute(['post_id' => $postId, 'tag_id' => $tagId]);
    }
}

/**
 * @param list<int> $postIds
 * @return array<int, list<array{slug: string, name: string}>>
 */
function tags_for_posts(PDO $pdo, array $postIds): array
{
    $postIds = array_values(array_filter(array_map('intval', $postIds), static fn (int $id): bool => $id > 0));
    if ($postIds === []) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($postIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT pt.post_id, t.slug, t.name
         FROM post_tags pt
         INNER JOIN tags t ON t.id = pt.tag_id
         WHERE pt.post_id IN ({$placeholders})
         ORDER BY t.name ASC"
    );
    $stmt->execute($postIds);

    $map = [];
    foreach ($stmt->fetchAll() as $row) {
        $id = (int) $row['post_id'];
        $map[$id][] = ['slug' => (string) $row['slug'], 'name' => (string) $row['name']];
    }

    return $map;
}

/**
 * @param list<array<string, mixed>> $posts
 * @return list<array<string, mixed>>
 */
function posts_attach_tags(PDO $pdo, array $posts): array
{
    $ids = [];
    foreach ($posts as $post) {
        $ids[] = (int) ($post['id'] ?? 0);
    }
    $byPost = tags_for_posts($pdo, $ids);
    foreach ($posts as $index => $post) {
        $posts[$index]['tags'] = $byPost[(int) ($post['id'] ?? 0)] ?? [];
    }

    return $posts;
}

function tags_get_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare('SELECT id, slug, name FROM tags WHERE slug = :slug LIMIT 1');
    $stmt->execute(['slug' => $slug]);
    $tag = $stmt->fetch();

    return $tag !== false ? $tag : null;
}

/**
 * @return list<array{slug: string, name: string, uses: int}>
 */
function tags_list_popular(PDO $pdo, int $limit = 12): array
{
    $stmt = $pdo->prepare(
        'SELECT t.slug, t.name, COUNT(*) AS uses
         FROM tags t
         INNER JOIN post_tags pt ON pt.tag_id = t.id
         INNER JOIN posts p ON p.id = pt.post_id AND p.status = :status
         GROUP BY t.id, t.slug, t.name
         ORDER BY uses DESC, t.name ASC
         LIMIT :limit'
    );
    $stmt->bindValue('status', 'published');
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function posts_count_published_in_section(PDO $pdo, string $section): int
{
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM posts WHERE status = 'published' AND section = :section");
    $stmt->execute(['section' => $section]);

    return (int) $stmt->fetchColumn();
}

function posts_count_published_with_tag(PDO $pdo, string $slug): int
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM posts p
         INNER JOIN post_tags pt ON pt.post_id = p.id
         INNER JOIN tags t ON t.id = pt.tag_id
         WHERE p.status = 'published' AND t.slug = :slug"
    );
    $stmt->execute(['slug' => $slug]);

    return (int) $stmt->fetchColumn();
}

/**
 * @return list<array<string, mixed>>
 */
function posts_list_published_in_section(PDO $pdo, string $section, int $page, int $perPage = POSTS_PER_PAGE): array
{
    $offset = max(0, ($page - 1) * $perPage);
    $sql = posts_translation_sql('p');
    $stmt = $pdo->prepare(
        'SELECT p.id, ' . $sql['title'] . ' AS title, ' . $sql['body'] . ' AS body, p.section, p.published_at
         FROM posts p ' . $sql['join'] . '
         WHERE p.status = :status AND p.section = :section
         ORDER BY p.published_at DESC, p.id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue('status', 'published');
    if (posts_active_lang() !== 'pt') {
        $stmt->bindValue('lang', posts_active_lang());
    }
    $stmt->bindValue('section', $section);
    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * @return list<array<string, mixed>>
 */
function posts_list_published_with_tag(PDO $pdo, string $slug, int $page, int $perPage = POSTS_PER_PAGE): array
{
    $offset = max(0, ($page - 1) * $perPage);
    $sql = posts_translation_sql('p');
    $stmt = $pdo->prepare(
        'SELECT p.id, ' . $sql['title'] . ' AS title, ' . $sql['body'] . ' AS body, p.section, p.published_at
         FROM posts p ' . $sql['join'] . '
         INNER JOIN post_tags pt ON pt.post_id = p.id
         INNER JOIN tags t ON t.id = pt.tag_id
         WHERE p.status = :status AND t.slug = :slug
         ORDER BY p.published_at DESC, p.id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue('status', 'published');
    if (posts_active_lang() !== 'pt') {
        $stmt->bindValue('lang', posts_active_lang());
    }
    $stmt->bindValue('slug', $slug);
    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}
