<?php

declare(strict_types=1);

require_once __DIR__ . '/i18n.php';

const POSTS_PER_PAGE = 16;
const FEATURED_CAROUSEL_COUNT = 8;
const RSS_ITEMS_LIMIT = 50;

function posts_active_lang(): string
{
    return normalize_site_lang(current_lang());
}

/**
 * @return array{join: string, title: string, body: string}
 */
function posts_translation_sql(string $postAlias = 'p'): array
{
    $lang = posts_active_lang();
    if ($lang === 'pt') {
        return [
            'join' => '',
            'title' => "{$postAlias}.title",
            'body' => "{$postAlias}.body",
        ];
    }

    return [
        'join' => " LEFT JOIN post_translations pt_lang
                    ON pt_lang.post_id = {$postAlias}.id
                   AND pt_lang.lang = :lang
                   AND pt_lang.status = 'published' ",
        'title' => 'COALESCE(pt_lang.title, ' . $postAlias . '.title)',
        'body' => 'COALESCE(pt_lang.body, ' . $postAlias . '.body)',
    ];
}

/**
 * @param array<string, mixed> $params
 */
function posts_bind_lang(array &$params): void
{
    $lang = posts_active_lang();
    if ($lang !== 'pt') {
        $params['lang'] = $lang;
    }
}

/** Coluna opcional (sql/020): o site continua funcionando antes da migração. */
function posts_has_featured_video_column(PDO $pdo): bool
{
    static $has = null;
    if ($has === null) {
        try {
            $has = $pdo->query("SHOW COLUMNS FROM posts LIKE 'featured_video_url'")->fetch() !== false;
        } catch (Throwable $e) {
            $has = false;
        }
    }

    return $has;
}

function posts_featured_video_select(PDO $pdo, string $alias = ''): string
{
    $prefix = $alias !== '' ? $alias . '.' : '';

    return posts_has_featured_video_column($pdo)
        ? ', ' . $prefix . 'featured_video_url'
        : ', NULL AS featured_video_url';
}

function posts_set_featured_video(PDO $pdo, int $id, ?string $url): void
{
    if (!posts_has_featured_video_column($pdo)) {
        return;
    }
    $stmt = $pdo->prepare('UPDATE posts SET featured_video_url = :url WHERE id = :id');
    $stmt->execute(['id' => $id, 'url' => $url]);
}

function posts_get_published_by_id(PDO $pdo, int $id): ?array
{
    $sql = posts_translation_sql('p');
    $stmt = $pdo->prepare(
        'SELECT p.id, ' . $sql['title'] . ' AS title, ' . $sql['body'] . ' AS body, p.section, p.status, p.published_at'
        . posts_featured_video_select($pdo, 'p') . '
         FROM posts p ' . $sql['join'] . '
         WHERE p.id = :id AND p.status = :status
         LIMIT 1'
    );
    $params = [
        'id' => $id,
        'status' => 'published',
    ];
    posts_bind_lang($params);
    $stmt->execute($params);
    $post = $stmt->fetch();

    return $post !== false ? $post : null;
}

function posts_get_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, title, body, section, status, published_at' . posts_featured_video_select($pdo) . '
         FROM posts
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);

    $post = $stmt->fetch();

    return $post !== false ? $post : null;
}

function posts_create(PDO $pdo, string $title, string $body, string $status, ?string $publishedAt, string $section = 'news'): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO posts (title, body, status, section, published_at)
         VALUES (:title, :body, :status, :section, :published_at)'
    );
    $stmt->execute([
        'title' => $title,
        'body' => $body,
        'status' => $status,
        'section' => $section,
        'published_at' => $publishedAt,
    ]);

    return (int) $pdo->lastInsertId();
}

function posts_update(PDO $pdo, int $id, string $title, string $body, string $status, ?string $publishedAt, string $section = 'news'): void
{
    $stmt = $pdo->prepare(
        'UPDATE posts
         SET title = :title,
             body = :body,
             status = :status,
             section = :section,
             published_at = :published_at
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'title' => $title,
        'body' => $body,
        'status' => $status,
        'section' => $section,
        'published_at' => $publishedAt,
    ]);
}

function post_translation_get(PDO $pdo, int $postId, string $lang): ?array
{
    $lang = normalize_site_lang($lang);
    if ($lang === 'pt') {
        return null;
    }

    $stmt = $pdo->prepare(
        'SELECT post_id, lang, title, body, status, updated_at
         FROM post_translations
         WHERE post_id = :post_id AND lang = :lang
         LIMIT 1'
    );
    $stmt->execute([
        'post_id' => $postId,
        'lang' => $lang,
    ]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

/**
 * @return array<string, array<string, mixed>>
 */
function post_translations_list(PDO $pdo, int $postId): array
{
    $stmt = $pdo->prepare(
        'SELECT post_id, lang, title, body, status, updated_at
         FROM post_translations
         WHERE post_id = :post_id
         ORDER BY lang ASC'
    );
    $stmt->execute(['post_id' => $postId]);

    $items = [];
    foreach ($stmt->fetchAll() as $row) {
        $items[(string) $row['lang']] = $row;
    }

    return $items;
}

function post_translation_upsert(PDO $pdo, int $postId, string $lang, string $title, string $body, string $status): void
{
    $lang = normalize_site_lang($lang);
    if ($lang === 'pt') {
        return;
    }

    $stmt = $pdo->prepare(
        'INSERT INTO post_translations (post_id, lang, title, body, status)
         VALUES (:post_id, :lang, :title, :body, :status)
         ON DUPLICATE KEY UPDATE
             title = VALUES(title),
             body = VALUES(body),
             status = VALUES(status)'
    );
    $stmt->execute([
        'post_id' => $postId,
        'lang' => $lang,
        'title' => $title,
        'body' => $body,
        'status' => $status,
    ]);
}

function posts_count_published(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM posts WHERE status = 'published'");

    return (int) $stmt->fetchColumn();
}

/**
 * @return list<array<string, mixed>>
 */
function posts_list_published(PDO $pdo, int $page, int $perPage = POSTS_PER_PAGE): array
{
    $offset = max(0, ($page - 1) * $perPage);
    $sql = posts_translation_sql('p');

    $stmt = $pdo->prepare(
        'SELECT p.id, ' . $sql['title'] . ' AS title, ' . $sql['body'] . ' AS body, p.section, p.published_at'
        . posts_featured_video_select($pdo, 'p') . '
         FROM posts p ' . $sql['join'] . '
         WHERE p.status = :status
         ORDER BY p.published_at DESC, p.id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue('status', 'published');
    if (posts_active_lang() !== 'pt') {
        $stmt->bindValue('lang', posts_active_lang());
    }
    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * @return list<array<string, mixed>>
 */
function posts_list_published_for_feed(PDO $pdo, int $limit = RSS_ITEMS_LIMIT): array
{
    $stmt = $pdo->prepare(
        'SELECT id, title, body, section, published_at
         FROM posts
         WHERE status = :status
         ORDER BY published_at DESC, id DESC
         LIMIT :limit'
    );
    $stmt->bindValue('status', 'published');
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * @return list<array<string, mixed>>
 */
function posts_list_recent(PDO $pdo, int $limit = 5, int $excludeId = 0): array
{
    $sql = posts_translation_sql('p');
    $stmt = $pdo->prepare(
        'SELECT p.id, ' . $sql['title'] . ' AS title, p.published_at
         FROM posts p ' . $sql['join'] . '
         WHERE p.status = :status AND p.id <> :exclude_id
         ORDER BY p.published_at DESC, p.id DESC
         LIMIT :limit'
    );
    $stmt->bindValue('status', 'published');
    if (posts_active_lang() !== 'pt') {
        $stmt->bindValue('lang', posts_active_lang());
    }
    $stmt->bindValue('exclude_id', $excludeId, PDO::PARAM_INT);
    $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

/**
 * @return list<array<string, mixed>>
 */
function posts_list_for_api(
    PDO $pdo,
    int $page,
    int $perPage = 20,
    ?string $section = null,
    ?string $status = null,
    ?string $q = null
): array {
    $page = max(1, $page);
    $perPage = max(1, min(100, $perPage));
    $offset = ($page - 1) * $perPage;

    $where = ['1=1'];
    $params = [];

    if ($section !== null && $section !== '') {
        $where[] = 'section = :section';
        $params['section'] = $section;
    }
    if ($status !== null && $status !== '') {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }
    if ($q !== null && trim($q) !== '') {
        $where[] = '(title LIKE :q OR body LIKE :q)';
        $params['q'] = '%' . trim($q) . '%';
    }

    $sql = 'SELECT id, title, section, status, published_at
            FROM posts
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY COALESCE(published_at, created_at) DESC, id DESC
            LIMIT :limit OFFSET :offset';

    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function posts_count_for_api(
    PDO $pdo,
    ?string $section = null,
    ?string $status = null,
    ?string $q = null
): int {
    $where = ['1=1'];
    $params = [];

    if ($section !== null && $section !== '') {
        $where[] = 'section = :section';
        $params['section'] = $section;
    }
    if ($status !== null && $status !== '') {
        $where[] = 'status = :status';
        $params['status'] = $status;
    }
    if ($q !== null && trim($q) !== '') {
        $where[] = '(title LIKE :q OR body LIKE :q)';
        $params['q'] = '%' . trim($q) . '%';
    }

    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM posts WHERE ' . implode(' AND ', $where)
    );
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}

/**
 * Busca posts publicados por título, corpo ou tag.
 *
 * @return list<array<string, mixed>>
 */
function posts_search_published(PDO $pdo, string $q, int $page, int $perPage = POSTS_PER_PAGE): array
{
    $q = trim($q);
    if (mb_strlen($q) < 2) {
        return [];
    }

    $page = max(1, $page);
    $perPage = max(1, min(50, $perPage));
    $offset = ($page - 1) * $perPage;
    $like = search_like_pattern($q);

    $sql = posts_translation_sql('p');
    $stmt = $pdo->prepare(
        "SELECT DISTINCT p.id, {$sql['title']} AS title, {$sql['body']} AS body, p.section, p.published_at
         FROM posts p {$sql['join']}
         LEFT JOIN post_tags pt ON pt.post_id = p.id
         LEFT JOIN tags t ON t.id = pt.tag_id
         WHERE p.status = 'published'
           AND (
             {$sql['title']} LIKE :q1 ESCAPE '\\\\'
             OR {$sql['body']} LIKE :q2 ESCAPE '\\\\'
             OR t.name LIKE :q3 ESCAPE '\\\\'
             OR t.slug LIKE :q4 ESCAPE '\\\\'
           )
         ORDER BY p.published_at DESC, p.id DESC
         LIMIT :limit OFFSET :offset"
    );
    if (posts_active_lang() !== 'pt') {
        $stmt->bindValue('lang', posts_active_lang());
    }
    $stmt->bindValue('q1', $like);
    $stmt->bindValue('q2', $like);
    $stmt->bindValue('q3', $like);
    $stmt->bindValue('q4', $like);
    $stmt->bindValue('limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue('offset', $offset, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function posts_count_search(PDO $pdo, string $q): int
{
    $q = trim($q);
    if (mb_strlen($q) < 2) {
        return 0;
    }

    $like = search_like_pattern($q);
    $sql = posts_translation_sql('p');
    $stmt = $pdo->prepare(
        "SELECT COUNT(DISTINCT p.id)
         FROM posts p {$sql['join']}
         LEFT JOIN post_tags pt ON pt.post_id = p.id
         LEFT JOIN tags t ON t.id = pt.tag_id
         WHERE p.status = 'published'
           AND (
             {$sql['title']} LIKE :q1 ESCAPE '\\\\'
             OR {$sql['body']} LIKE :q2 ESCAPE '\\\\'
             OR t.name LIKE :q3 ESCAPE '\\\\'
             OR t.slug LIKE :q4 ESCAPE '\\\\'
           )"
    );
    $params = [
        'q1' => $like,
        'q2' => $like,
        'q3' => $like,
        'q4' => $like,
    ];
    posts_bind_lang($params);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}
