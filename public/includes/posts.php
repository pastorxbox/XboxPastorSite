<?php

declare(strict_types=1);

const POSTS_PER_PAGE = 10;
const RSS_ITEMS_LIMIT = 50;

function posts_get_published_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, title, body, status, published_at
         FROM posts
         WHERE id = :id AND status = :status
         LIMIT 1'
    );
    $stmt->execute([
        'id' => $id,
        'status' => 'published',
    ]);

    $post = $stmt->fetch();

    return $post !== false ? $post : null;
}

function posts_get_by_id(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, title, body, status, published_at
         FROM posts
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);

    $post = $stmt->fetch();

    return $post !== false ? $post : null;
}

function posts_create(PDO $pdo, string $title, string $body, string $status, ?string $publishedAt): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO posts (title, body, status, published_at)
         VALUES (:title, :body, :status, :published_at)'
    );
    $stmt->execute([
        'title' => $title,
        'body' => $body,
        'status' => $status,
        'published_at' => $publishedAt,
    ]);

    return (int) $pdo->lastInsertId();
}

function posts_update(PDO $pdo, int $id, string $title, string $body, string $status, ?string $publishedAt): void
{
    $stmt = $pdo->prepare(
        'UPDATE posts
         SET title = :title,
             body = :body,
             status = :status,
             published_at = :published_at
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'title' => $title,
        'body' => $body,
        'status' => $status,
        'published_at' => $publishedAt,
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

    $stmt = $pdo->prepare(
        'SELECT id, title, body, published_at
         FROM posts
         WHERE status = :status
         ORDER BY published_at DESC, id DESC
         LIMIT :limit OFFSET :offset'
    );
    $stmt->bindValue('status', 'published');
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
        'SELECT id, title, body, published_at
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
