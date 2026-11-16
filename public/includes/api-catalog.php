<?php

declare(strict_types=1);

/**
 * API do catálogo Xbox (Fase 2) — espelho do Manager.
 * Contrato: nunca UPDATE gp_releases.price_brl a partir deste payload.
 */

function catalog_api_optional_datetime(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (!is_string($value)) {
        api_error(400, 'Invalid datetime field');
    }
    $timestamp = strtotime($value);
    if ($timestamp === false) {
        api_error(400, 'Invalid datetime field');
    }

    return date('Y-m-d H:i:s', $timestamp);
}

function catalog_api_decimal(mixed $value, string $field): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_int($value) || is_float($value)) {
        return number_format((float) $value, 2, '.', '');
    }
    if (!is_string($value) || !is_numeric($value)) {
        api_error(400, 'Invalid ' . $field);
    }

    return number_format((float) $value, 2, '.', '');
}

/**
 * @return array<string, mixed>
 */
function catalog_api_parse_upsert_payload(array $payload, string $managerGameIdFromPath): array
{
    $managerId = trim((string) ($payload['manager_game_id'] ?? $managerGameIdFromPath));
    if ($managerId === '' || $managerId !== $managerGameIdFromPath) {
        api_error(400, 'manager_game_id mismatch');
    }
    if (strlen($managerId) > 64) {
        api_error(400, 'manager_game_id too long');
    }

    $slug = trim((string) ($payload['slug'] ?? ''));
    if ($slug === '' || strlen($slug) > 190) {
        api_error(400, 'Invalid slug');
    }

    $title = api_normalize_title((string) ($payload['title'] ?? ''));
    if (function_exists('mb_strlen') ? mb_strlen($title) > 255 : strlen($title) > 255) {
        api_error(400, 'Title exceeds 255 characters');
    }

    $status = strtoupper(trim((string) ($payload['status'] ?? 'DRAFT')));
    if ($status === '') {
        $status = 'DRAFT';
    }
    if (strlen($status) > 32) {
        api_error(400, 'Invalid status');
    }

    $synopsis = null;
    if (array_key_exists('synopsis', $payload) && $payload['synopsis'] !== null) {
        if (!is_string($payload['synopsis'])) {
            api_error(400, 'Invalid synopsis');
        }
        $synopsis = trim($payload['synopsis']);
        if ($synopsis === '') {
            $synopsis = null;
        }
    }

    $coverUrl = api_optional_string($payload['cover_url'] ?? null, 1024);

    $franchiseName = null;
    $franchiseSlug = null;
    $franchise = $payload['franchise'] ?? null;
    if (is_array($franchise)) {
        $franchiseName = api_optional_string($franchise['name'] ?? null, 255);
        $franchiseSlug = api_optional_string($franchise['slug'] ?? null, 190);
    }

    // Contrato Fase 2: o cliente deve declarar que não quer sobrescrever price_brl.
    // Aceitamos ausência só se o campo vier true quando presente; rejeitamos false.
    if (array_key_exists('do_not_overwrite_price_brl', $payload)
        && $payload['do_not_overwrite_price_brl'] !== true
        && $payload['do_not_overwrite_price_brl'] !== 1
        && $payload['do_not_overwrite_price_brl'] !== '1'
    ) {
        api_error(400, 'do_not_overwrite_price_brl must be true');
    }

    $productsRaw = $payload['products'] ?? [];
    if (!is_array($productsRaw)) {
        api_error(400, 'Invalid products');
    }

    $products = [];
    foreach ($productsRaw as $item) {
        if (!is_array($item)) {
            api_error(400, 'Invalid product item');
        }
        $storeProductId = trim((string) ($item['store_product_id'] ?? ''));
        if ($storeProductId === '' || strlen($storeProductId) > 64) {
            api_error(400, 'Invalid store_product_id');
        }

        $market = strtoupper(trim((string) ($item['market'] ?? 'BR')));
        if ($market === '' || strlen($market) > 8) {
            api_error(400, 'Invalid market');
        }

        $currency = strtoupper(trim((string) ($item['currency'] ?? 'BRL')));
        if ($currency === '' || strlen($currency) > 8) {
            api_error(400, 'Invalid currency');
        }

        $productType = strtoupper(trim((string) ($item['product_type'] ?? 'UNKNOWN')));
        if ($productType === '') {
            $productType = 'UNKNOWN';
        }
        if (strlen($productType) > 32) {
            api_error(400, 'Invalid product_type');
        }

        $priceCurrent = null;
        $priceReference = null;
        $priceStatus = null;
        $priceCheckedAt = null;
        $price = $item['price'] ?? null;
        if (is_array($price)) {
            $priceCurrent = catalog_api_decimal($price['current'] ?? null, 'price.current');
            $priceReference = catalog_api_decimal($price['reference'] ?? null, 'price.reference');
            $priceStatus = api_optional_string($price['status'] ?? null, 32);
            $priceCheckedAt = catalog_api_optional_datetime($price['checked_at'] ?? null);
        }

        $products[] = [
            'store_product_id' => $storeProductId,
            'store_url' => api_optional_string($item['store_url'] ?? null, 1024),
            'edition_name' => api_optional_string($item['edition_name'] ?? null, 255),
            'product_type' => $productType,
            'market' => $market,
            'currency' => $currency,
            'active' => api_bool_flag($item['active'] ?? true),
            'price_current' => $priceCurrent,
            'price_reference' => $priceReference,
            'price_status' => $priceStatus,
            'price_checked_at' => $priceCheckedAt,
        ];
    }

    return [
        'id' => $managerId,
        'manager_game_id' => $managerId,
        'slug' => $slug,
        'title' => $title,
        'synopsis' => $synopsis,
        'cover_url' => $coverUrl,
        'status' => $status,
        'franchise_name' => $franchiseName,
        'franchise_slug' => $franchiseSlug,
        'synced_at' => date('Y-m-d H:i:s'),
        'products' => $products,
    ];
}

function api_handle_catalog_game_upsert(PDO $pdo, array $config, string $managerGameId): never
{
    api_require_method('PUT');
    api_require_https($config);
    api_require_auth($config);

    $managerGameId = trim($managerGameId);
    if ($managerGameId === '' || strlen($managerGameId) > 64) {
        api_error(400, 'Invalid manager game id');
    }

    $data = catalog_api_parse_upsert_payload(api_read_json_body(), $managerGameId);

    try {
        $pdo->beginTransaction();
        $id = catalog_upsert_game($pdo, $data);
        catalog_replace_products($pdo, $id, $data['products']);
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        api_error(500, 'Catalog upsert failed');
    }

    api_json(200, [
        'id' => $id,
        'slug' => $data['slug'],
        'manager_game_id' => $data['manager_game_id'],
    ]);
}

function api_handle_gamepass_catalog_link(PDO $pdo, array $config, int $releaseId): never
{
    api_require_method('PUT');
    api_require_https($config);
    api_require_auth($config);

    if ($releaseId <= 0 || calendar_get_gamepass($pdo, $releaseId) === null) {
        api_error(404, 'Game Pass release not found');
    }

    if (!catalog_gamepass_has_catalog_column($pdo)) {
        api_error(503, 'catalog_game_id column missing; run sql/022_catalog_fase2.sql');
    }

    $payload = api_read_json_body();
    $catalogGameId = null;
    if (array_key_exists('catalog_game_id', $payload) && $payload['catalog_game_id'] !== null) {
        if (!is_string($payload['catalog_game_id']) && !is_numeric($payload['catalog_game_id'])) {
            api_error(400, 'Invalid catalog_game_id');
        }
        $catalogGameId = trim((string) $payload['catalog_game_id']);
        if ($catalogGameId === '') {
            $catalogGameId = null;
        } elseif (strlen($catalogGameId) > 64) {
            api_error(400, 'catalog_game_id too long');
        } elseif (
            catalog_get_game_by_id($pdo, $catalogGameId) === null
            && catalog_get_game_by_manager_id($pdo, $catalogGameId) === null
        ) {
            api_error(404, 'Catalog game not found');
        }
    }

    catalog_link_gamepass($pdo, $releaseId, $catalogGameId);

    api_json(200, [
        'id' => $releaseId,
        'catalog_game_id' => $catalogGameId,
    ]);
}

function catalog_get_game_by_id(PDO $pdo, string $id): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM catalog_games WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
}
