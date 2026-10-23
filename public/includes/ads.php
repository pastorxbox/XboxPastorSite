<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

const AD_SLOT_SLUGS = [
    'header',
    'sidebar_top',
    'sidebar_bottom',
    'in_feed',
    'in_article',
];

/** @return array<string, array{width_px: int, height_px: int}> */
function ads_default_dimensions(): array
{
    return [
        'header' => ['width_px' => 1200, 'height_px' => 250],
        'sidebar_top' => ['width_px' => 340, 'height_px' => 230],
        'sidebar_bottom' => ['width_px' => 340, 'height_px' => 280],
        'in_feed' => ['width_px' => 832, 'height_px' => 250],
        'in_article' => ['width_px' => 832, 'height_px' => 250],
    ];
}

/**
 * @return array{width_px: int, height_px: int}
 */
function ads_resolve_dimensions(string $slug, int $widthPx, int $heightPx): array
{
    $defaults = ads_default_dimensions()[$slug] ?? ['width_px' => 832, 'height_px' => 250];
    return [
        'width_px' => $widthPx > 0 ? $widthPx : $defaults['width_px'],
        'height_px' => $heightPx > 0 ? $heightPx : $defaults['height_px'],
    ];
}

/**
 * @return array<string, array{id: int, slug: string, label: string, enabled: bool, width_px: int, height_px: int, slides: list<array<string, mixed>>}>
 */
function ads_public_store(?array $set = null): array
{
    static $cache = [];
    if ($set !== null) {
        $cache = $set;
    }

    return $cache;
}

function ads_is_bootstrapped(?bool $set = null): bool
{
    static $loaded = false;
    if ($set !== null) {
        $loaded = $set;
    }

    return $loaded;
}

function ads_bootstrap(PDO $pdo): void
{
    if (ads_is_bootstrapped()) {
        return;
    }

    $cache = [];
    $slots = $pdo->query(
        'SELECT id, slug, label, enabled, width_px, height_px
         FROM ad_slots
         ORDER BY id ASC'
    )->fetchAll();

    $slidesBySlot = [];
    $slideStmt = $pdo->query(
        "SELECT id, slot_id, image_url, link_url, duration_sec, sort_order, status
         FROM ad_slides
         WHERE status = 'published'
         ORDER BY sort_order ASC, id ASC"
    );
    foreach ($slideStmt->fetchAll() as $slide) {
        $slotId = (int) $slide['slot_id'];
        $slidesBySlot[$slotId][] = $slide;
    }

    foreach ($slots as $slot) {
        $slug = (string) $slot['slug'];
        $slotId = (int) $slot['id'];
        $enabled = !empty($slot['enabled']);
        $slides = $enabled ? ($slidesBySlot[$slotId] ?? []) : [];
        $dims = ads_resolve_dimensions(
            $slug,
            (int) ($slot['width_px'] ?? 0),
            (int) ($slot['height_px'] ?? 0)
        );
        $cache[$slug] = [
            'id' => $slotId,
            'slug' => $slug,
            'label' => (string) $slot['label'],
            'enabled' => $enabled && $slides !== [],
            'width_px' => $dims['width_px'],
            'height_px' => $dims['height_px'],
            'slides' => $slides,
        ];
    }

    ads_public_store($cache);
    ads_is_bootstrapped(true);
}

/**
 * @return array{id: int, slug: string, label: string, enabled: bool, width_px: int, height_px: int, slides: list<array<string, mixed>>}|null
 */
function ads_get_public_slot(string $slug): ?array
{
    $cache = ads_public_store();
    if (!isset($cache[$slug])) {
        return null;
    }

    $slot = $cache[$slug];
    if (!$slot['enabled'] || $slot['slides'] === []) {
        return null;
    }

    return $slot;
}

/**
 * @return list<array<string, mixed>>
 */
function ads_list_slots_admin(PDO $pdo): array
{
    $slots = $pdo->query(
        'SELECT id, slug, label, enabled, width_px, height_px, created_at, updated_at
         FROM ad_slots
         ORDER BY id ASC'
    )->fetchAll();

    $slides = $pdo->query(
        'SELECT id, slot_id, image_url, link_url, duration_sec, sort_order, status, created_at, updated_at
         FROM ad_slides
         ORDER BY sort_order ASC, id ASC'
    )->fetchAll();

    $bySlot = [];
    foreach ($slides as $slide) {
        $bySlot[(int) $slide['slot_id']][] = [
            'id' => (int) $slide['id'],
            'image_url' => (string) $slide['image_url'],
            'link_url' => $slide['link_url'] !== null ? (string) $slide['link_url'] : null,
            'duration_sec' => (int) $slide['duration_sec'],
            'sort_order' => (int) $slide['sort_order'],
            'status' => (string) $slide['status'],
            'created_at' => (string) $slide['created_at'],
            'updated_at' => (string) $slide['updated_at'],
        ];
    }

    $out = [];
    foreach ($slots as $slot) {
        $id = (int) $slot['id'];
        $slug = (string) $slot['slug'];
        $dims = ads_resolve_dimensions(
            $slug,
            (int) ($slot['width_px'] ?? 0),
            (int) ($slot['height_px'] ?? 0)
        );
        $out[] = [
            'id' => $id,
            'slug' => $slug,
            'label' => (string) $slot['label'],
            'enabled' => !empty($slot['enabled']),
            'width_px' => $dims['width_px'],
            'height_px' => $dims['height_px'],
            'created_at' => (string) $slot['created_at'],
            'updated_at' => (string) $slot['updated_at'],
            'slides' => $bySlot[$id] ?? [],
        ];
    }

    return $out;
}

function ads_get_slot_by_slug(PDO $pdo, string $slug): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, slug, label, enabled, width_px, height_px, created_at, updated_at
         FROM ad_slots
         WHERE slug = :slug
         LIMIT 1'
    );
    $stmt->execute(['slug' => $slug]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function ads_get_slide(PDO $pdo, int $id): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, slot_id, image_url, link_url, duration_sec, sort_order, status, created_at, updated_at
         FROM ad_slides
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    return $row !== false ? $row : null;
}

function ads_set_slot_enabled(PDO $pdo, string $slug, bool $enabled): void
{
    ads_update_slot($pdo, $slug, ['enabled' => $enabled]);
}

/**
 * @param array{enabled?: bool, width_px?: int, height_px?: int} $fields
 */
function ads_update_slot(PDO $pdo, string $slug, array $fields): void
{
    $sets = [];
    $params = ['slug' => $slug];

    if (array_key_exists('enabled', $fields)) {
        $sets[] = 'enabled = :enabled';
        $params['enabled'] = !empty($fields['enabled']) ? 1 : 0;
    }
    if (array_key_exists('width_px', $fields)) {
        $sets[] = 'width_px = :width_px';
        $params['width_px'] = (int) $fields['width_px'];
    }
    if (array_key_exists('height_px', $fields)) {
        $sets[] = 'height_px = :height_px';
        $params['height_px'] = (int) $fields['height_px'];
    }

    if ($sets === []) {
        return;
    }

    $stmt = $pdo->prepare(
        'UPDATE ad_slots SET ' . implode(', ', $sets) . ' WHERE slug = :slug'
    );
    $stmt->execute($params);
}

/**
 * @param array{
 *   image_url: string,
 *   link_url: ?string,
 *   duration_sec: int,
 *   sort_order: int,
 *   status: string
 * } $data
 */
function ads_create_slide(PDO $pdo, int $slotId, array $data): int
{
    $stmt = $pdo->prepare(
        'INSERT INTO ad_slides (slot_id, image_url, link_url, duration_sec, sort_order, status)
         VALUES (:slot_id, :image_url, :link_url, :duration_sec, :sort_order, :status)'
    );
    $stmt->execute([
        'slot_id' => $slotId,
        'image_url' => $data['image_url'],
        'link_url' => $data['link_url'],
        'duration_sec' => $data['duration_sec'],
        'sort_order' => $data['sort_order'],
        'status' => $data['status'],
    ]);

    return (int) $pdo->lastInsertId();
}

/**
 * @param array{
 *   image_url: string,
 *   link_url: ?string,
 *   duration_sec: int,
 *   sort_order: int,
 *   status: string
 * } $data
 */
function ads_update_slide(PDO $pdo, int $id, array $data): void
{
    $stmt = $pdo->prepare(
        'UPDATE ad_slides SET
            image_url = :image_url,
            link_url = :link_url,
            duration_sec = :duration_sec,
            sort_order = :sort_order,
            status = :status
         WHERE id = :id'
    );
    $stmt->execute([
        'id' => $id,
        'image_url' => $data['image_url'],
        'link_url' => $data['link_url'],
        'duration_sec' => $data['duration_sec'],
        'sort_order' => $data['sort_order'],
        'status' => $data['status'],
    ]);
}

function ads_delete_slide(PDO $pdo, int $id): void
{
    $stmt = $pdo->prepare('DELETE FROM ad_slides WHERE id = :id');
    $stmt->execute(['id' => $id]);
}

function ads_slot_css_modifier(string $slug): string
{
    return match ($slug) {
        'header' => 'header',
        'sidebar_top', 'sidebar_bottom' => 'side',
        'in_feed', 'in_article' => 'inline',
        default => 'inline',
    };
}

function render_ad_slot(string $slug): string
{
    $slot = ads_get_public_slot($slug);
    if ($slot === null) {
        return '';
    }

    $modifier = ads_slot_css_modifier($slug);
    $domId = match ($slug) {
        'header' => 'px-banner-header',
        'sidebar_top' => 'px-banner-side-top',
        'sidebar_bottom' => 'px-banner-side-bottom',
        'in_feed' => 'px-banner-in-feed',
        'in_article' => 'px-banner-in-article',
        default => 'px-banner-' . preg_replace('/[^a-z0-9_-]/i', '', $slug),
    };

    $widthPx = max(40, (int) ($slot['width_px'] ?? 0));
    $heightPx = max(40, (int) ($slot['height_px'] ?? 0));
    $slideCount = count($slot['slides']);
    $isSide = str_starts_with($slug, 'sidebar_');
    // Variáveis CSS para todos os slots.
    $styleParts = [
        '--px-w:' . $widthPx . 'px',
        '--px-h:' . $heightPx . 'px',
    ];
    // Largura/altura fixas só na lateral — no header isso corta a arte no mobile.
    if ($isSide) {
        $styleParts[] = 'width:' . $widthPx . 'px';
        $styleParts[] = 'height:' . $heightPx . 'px';
        $styleParts[] = 'max-width:100%';
        $styleParts[] = 'max-height:' . $heightPx . 'px';
    }
    if ($slug === 'in_feed') {
        $styleParts[] = 'grid-column:1 / -1';
    }
    $extraClass = $slug === 'in_feed' ? ' featured' : '';
    if ($slideCount >= 2) {
        $extraClass .= ' px-banner--multi';
    }
    if ($heightPx > 0) {
        $extraClass .= ' px-banner--max-h';
    }
    $extraStyle = ' style="' . e(implode(';', $styleParts)) . '"';

    ob_start();
    ?>
<div id="<?= e($domId) ?>" class="px-banner px-banner--<?= e($modifier) ?><?= $extraClass ?>" data-px-banner="<?= e($slug) ?>" data-px-width="<?= $widthPx ?>"<?= $extraStyle ?>>
    <?php foreach ($slot['slides'] as $index => $slide): ?>
        <?php
        $imageUrl = (string) $slide['image_url'];
        $linkUrl = trim((string) ($slide['link_url'] ?? ''));
        $duration = max(1, (int) ($slide['duration_sec'] ?? 5));
        $active = $index === 0 ? ' is-active' : '';
        $tag = $linkUrl !== '' ? 'a' : 'div';
        $hrefAttr = $linkUrl !== ''
            ? ' href="' . e($linkUrl) . '" target="_blank" rel="noopener noreferrer"'
            : '';
        ?>
        <<?= $tag ?> class="px-banner__item<?= $active ?>" data-duration="<?= e((string) $duration) ?>"<?= $hrefAttr ?>>
            <img src="<?= e($imageUrl) ?>" alt="" loading="<?= $index === 0 ? 'eager' : 'lazy' ?>">
        </<?= $tag ?>>
    <?php endforeach; ?>
</div>
    <?php

    return (string) ob_get_clean();
}
