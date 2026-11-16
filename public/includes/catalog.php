<?php

declare(strict_types=1);

/**
 * Catálogo Xbox espelhado do Manager (Fase 2).
 * Nunca atualiza gp_releases.price_brl a partir destes dados.
 */

/**
 * @param array<string, mixed> $data
 */
function catalog_upsert_game(PDO $pdo, array $data): string
{
    $id = (string) $data['id'];
    $stmt = $pdo->prepare(
        'INSERT INTO catalog_games (
            id, manager_game_id, slug, title, synopsis, cover_url, status,
            franchise_name, franchise_slug, synced_at
         ) VALUES (
            :id, :manager_game_id, :slug, :title, :synopsis, :cover_url, :status,
            :franchise_name, :franchise_slug, :synced_at
         )
         ON DUPLICATE KEY UPDATE
            slug = VALUES(slug),
            title = VALUES(title),
            synopsis = VALUES(synopsis),
            cover_url = VALUES(cover_url),
            status = VALUES(status),
            franchise_name = VALUES(franchise_name),
            franchise_slug = VALUES(franchise_slug),
            synced_at = VALUES(synced_at),
            updated_at = CURRENT_TIMESTAMP'
    );
    $stmt->execute([
        ':id' => $id,
        ':manager_game_id' => (string) $data['manager_game_id'],
        ':slug' => (string) $data['slug'],
        ':title' => (string) $data['title'],
        ':synopsis' => $data['synopsis'],
        ':cover_url' => $data['cover_url'],
        ':status' => (string) $data['status'],
        ':franchise_name' => $data['franchise_name'],
        ':franchise_slug' => $data['franchise_slug'],
        ':synced_at' => $data['synced_at'],
    ]);

    return $id;
}

/**
 * @param list<array<string, mixed>> $products
 */
function catalog_replace_products(PDO $pdo, string $catalogGameId, array $products): void
{
    $deactivate = $pdo->prepare(
        'UPDATE catalog_store_products SET active = 0, updated_at = CURRENT_TIMESTAMP
         WHERE catalog_game_id = :catalog_game_id'
    );
    $deactivate->execute([':catalog_game_id' => $catalogGameId]);

    $upsert = $pdo->prepare(
        'INSERT INTO catalog_store_products (
            catalog_game_id, store_product_id, store_url, edition_name, product_type,
            market, currency, active, price_current, price_reference, price_status, price_checked_at
         ) VALUES (
            :catalog_game_id, :store_product_id, :store_url, :edition_name, :product_type,
            :market, :currency, :active, :price_current, :price_reference, :price_status, :price_checked_at
         )
         ON DUPLICATE KEY UPDATE
            catalog_game_id = VALUES(catalog_game_id),
            store_url = VALUES(store_url),
            edition_name = VALUES(edition_name),
            product_type = VALUES(product_type),
            currency = VALUES(currency),
            active = VALUES(active),
            price_current = VALUES(price_current),
            price_reference = VALUES(price_reference),
            price_status = VALUES(price_status),
            price_checked_at = VALUES(price_checked_at),
            updated_at = CURRENT_TIMESTAMP'
    );

    $mirror = $pdo->prepare(
        'INSERT INTO catalog_price_mirror (
            store_product_id, market, current_price, reference_price, currency, status, checked_at
         ) VALUES (
            :store_product_id, :market, :current_price, :reference_price, :currency, :status, :checked_at
         )
         ON DUPLICATE KEY UPDATE
            current_price = VALUES(current_price),
            reference_price = VALUES(reference_price),
            currency = VALUES(currency),
            status = VALUES(status),
            checked_at = VALUES(checked_at),
            updated_at = CURRENT_TIMESTAMP'
    );

    foreach ($products as $product) {
        $upsert->execute([
            ':catalog_game_id' => $catalogGameId,
            ':store_product_id' => (string) $product['store_product_id'],
            ':store_url' => $product['store_url'],
            ':edition_name' => $product['edition_name'],
            ':product_type' => (string) $product['product_type'],
            ':market' => (string) $product['market'],
            ':currency' => (string) $product['currency'],
            ':active' => (int) $product['active'],
            ':price_current' => $product['price_current'],
            ':price_reference' => $product['price_reference'],
            ':price_status' => $product['price_status'],
            ':price_checked_at' => $product['price_checked_at'],
        ]);

        if ($product['price_current'] === null && $product['price_reference'] === null) {
            continue;
        }

        $mirror->execute([
            ':store_product_id' => (string) $product['store_product_id'],
            ':market' => (string) $product['market'],
            ':current_price' => $product['price_current'],
            ':reference_price' => $product['price_reference'],
            ':currency' => (string) $product['currency'],
            ':status' => $product['price_status'],
            ':checked_at' => $product['price_checked_at'],
        ]);
    }
}

function catalog_get_game_by_manager_id(PDO $pdo, string $managerGameId): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM catalog_games WHERE manager_game_id = :id LIMIT 1');
    $stmt->execute([':id' => $managerGameId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}

/**
 * Atualiza somente o vínculo catalog_game_id. Nunca altera price_brl.
 */
function catalog_link_gamepass(PDO $pdo, int $releaseId, ?string $catalogGameId): void
{
    $stmt = $pdo->prepare(
        'UPDATE gp_releases SET catalog_game_id = :catalog_game_id WHERE id = :id'
    );
    $stmt->execute([
        ':catalog_game_id' => $catalogGameId,
        ':id' => $releaseId,
    ]);
}

function catalog_gamepass_has_catalog_column(PDO $pdo): bool
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM gp_releases LIKE 'catalog_game_id'");
        $cached = $stmt !== false && $stmt->fetch(PDO::FETCH_ASSOC) !== false;
    } catch (Throwable) {
        $cached = false;
    }

    return $cached;
}
