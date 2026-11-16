<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/catalog.php';

/**
 * Vitrine pública /jogos — preços informativos; sem ícone na home/nav nesta fase de teste.
 */

function site_jogos_url(array $config, int $page = 1, ?string $slug = null): string
{
    $base = rtrim((string) ($config['site_base_url'] ?? ''), '/');
    $url = $base . '/jogos';
    if (is_string($slug) && $slug !== '') {
        $url .= '/' . rawurlencode($slug);
    }

    $params = [];
    if ($page > 1 && ($slug === null || $slug === '')) {
        $params['paged'] = $page;
    }
    if (current_lang() !== 'pt') {
        $params['lang'] = current_lang();
    }

    if ($params === []) {
        return $url;
    }

    return $url . '?' . http_build_query($params);
}

/**
 * @param list<array<string, mixed>> $games
 */
function render_jogos_list_content(array $config, array $games, int $page, int $totalPages): string
{
    $heading = t('jogos.title');
    $lead = t('jogos.lead');
    $disclaimer = t('jogos.disclaimer');
    $empty = t('jogos.empty');

    ob_start();
    ?>
    <section class="jogos-vitrine" aria-labelledby="jogos-heading">
        <header class="section-head">
            <h1 id="jogos-heading"><?= e($heading) ?></h1>
            <p class="lead"><?= e($lead) ?></p>
        </header>
        <p class="jogos-disclaimer" role="note"><?= e($disclaimer) ?></p>
        <?php if ($games === []): ?>
            <p class="jogos-empty"><?= e($empty) ?></p>
        <?php else: ?>
            <div class="jogos-grid">
                <?php foreach ($games as $game): ?>
                    <?= render_jogos_card($config, $game) ?>
                <?php endforeach; ?>
            </div>
            <?= render_pagination_nav(
                $page,
                $totalPages,
                static fn (int $n): string => site_jogos_url($config, $n)
            ) ?>
        <?php endif; ?>
    </section>
    <?php

    return (string) ob_get_clean();
}

/**
 * @param array<string, mixed> $game
 */
function render_jogos_card(array $config, array $game): string
{
    $slug = (string) ($game['slug'] ?? '');
    $title = (string) ($game['title'] ?? '');
    $url = site_jogos_url($config, 1, $slug);
    $cover = trim((string) ($game['cover_url'] ?? ''));
    $product = is_array($game['list_product'] ?? null) ? $game['list_product'] : null;
    $priceHtml = '';
    if (is_array($product)) {
        $formatted = catalog_format_price($product['price_current'] ?? null, (string) ($product['currency'] ?? 'BRL'));
        if ($formatted !== '') {
            $priceHtml = $formatted;
        }
    }

    ob_start();
    ?>
    <article class="jogos-card">
        <a class="jogos-card__media" href="<?= e($url) ?>">
            <?php if ($cover !== ''): ?>
                <img src="<?= e($cover) ?>" alt="" loading="lazy">
            <?php else: ?>
                <span class="jogos-card__ph" aria-hidden="true">🎮</span>
            <?php endif; ?>
        </a>
        <div class="jogos-card__body">
            <h2 class="jogos-card__title"><a href="<?= e($url) ?>"><?= e($title) ?></a></h2>
            <?php if ($priceHtml !== ''): ?>
                <p class="jogos-card__price"><?= e($priceHtml) ?></p>
            <?php else: ?>
                <p class="jogos-card__price jogos-card__price--na"><?= e(t('jogos.price_na')) ?></p>
            <?php endif; ?>
        </div>
    </article>
    <?php

    return (string) ob_get_clean();
}

/**
 * @param array<string, mixed> $game
 */
function render_jogos_detail_content(array $config, array $game): string
{
    $title = (string) ($game['title'] ?? '');
    $synopsis = trim((string) ($game['synopsis'] ?? ''));
    $cover = trim((string) ($game['cover_url'] ?? ''));
    $franchise = trim((string) ($game['franchise_name'] ?? ''));
    $products = is_array($game['products'] ?? null) ? $game['products'] : [];
    $listUrl = site_jogos_url($config);

    ob_start();
    ?>
    <article class="jogos-detail">
        <p class="jogos-detail__back"><a href="<?= e($listUrl) ?>">← <?= e(t('jogos.back')) ?></a></p>
        <header class="jogos-detail__head">
            <?php if ($cover !== ''): ?>
                <div class="jogos-detail__cover">
                    <img src="<?= e($cover) ?>" alt="" loading="eager">
                </div>
            <?php endif; ?>
            <div class="jogos-detail__intro">
                <h1><?= e($title) ?></h1>
                <?php if ($franchise !== ''): ?>
                    <p class="jogos-detail__franchise"><?= e($franchise) ?></p>
                <?php endif; ?>
                <p class="jogos-disclaimer" role="note"><?= e(t('jogos.disclaimer')) ?></p>
            </div>
        </header>
        <?php if ($synopsis !== ''): ?>
            <div class="jogos-detail__synopsis">
                <p><?= nl2br(e($synopsis)) ?></p>
            </div>
        <?php endif; ?>
        <section class="jogos-detail__products" aria-labelledby="jogos-editions">
            <h2 id="jogos-editions"><?= e(t('jogos.editions')) ?></h2>
            <?php if ($products === []): ?>
                <p class="jogos-empty"><?= e(t('jogos.no_products')) ?></p>
            <?php else: ?>
                <ul class="jogos-editions">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $edition = trim((string) ($product['edition_name'] ?? ''));
                        if ($edition === '') {
                            $edition = (string) ($product['product_type'] ?? 'GAME');
                        }
                        $price = catalog_format_price($product['price_current'] ?? null, (string) ($product['currency'] ?? 'BRL'));
                        $ref = catalog_format_price($product['price_reference'] ?? null, (string) ($product['currency'] ?? 'BRL'));
                        $storeUrl = trim((string) ($product['store_url'] ?? ''));
                        $status = strtoupper(trim((string) ($product['price_status'] ?? '')));
                        ?>
                        <li class="jogos-edition">
                            <div class="jogos-edition__main">
                                <strong><?= e($edition) ?></strong>
                                <?php if ($price !== ''): ?>
                                    <span class="jogos-edition__price"><?= e($price) ?></span>
                                <?php else: ?>
                                    <span class="jogos-edition__price jogos-edition__price--na"><?= e(t('jogos.price_na')) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if ($ref !== '' && $ref !== $price): ?>
                                <p class="jogos-edition__ref"><?= e(t('jogos.ref_price', ['price' => $ref])) ?></p>
                            <?php endif; ?>
                            <?php if ($status === 'STALE'): ?>
                                <p class="jogos-edition__stale"><?= e(t('jogos.stale')) ?></p>
                            <?php endif; ?>
                            <?php if ($storeUrl !== ''): ?>
                                <p class="jogos-edition__store">
                                    <a href="<?= e($storeUrl) ?>" target="_blank" rel="noopener noreferrer"><?= e(t('jogos.store')) ?></a>
                                </p>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </section>
    </article>
    <?php

    return (string) ob_get_clean();
}
