<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/posts.php';
require_once __DIR__ . '/share.php';
require_once __DIR__ . '/calendar.php';

/**
 * @param array<string, mixed> $post
 */
function render_post_card(array $config, array $post, bool $featured = false, string $extraClass = ''): string
{
    $postId = (int) $post['id'];
    $title = (string) $post['title'];
    $body = (string) $post['body'];
    $url = site_post_url($config, $postId);
    $excerpt = excerpt_from_html($body, $featured ? 220 : 130);
    $date = format_post_date(isset($post['published_at']) ? (string) $post['published_at'] : null);
    $thumb = first_image_src($body);
    $videoId = youtube_video_id(isset($post['featured_video_url']) ? (string) $post['featured_video_url'] : null);
    if ($thumb === null && $videoId !== null) {
        $thumb = youtube_thumbnail_url($videoId);
    }
    // Carrossel da Home: 10 s do vídeo a partir dos 30 s (iframe injetado pelo vitrine.js no slide ativo).
    $carouselVideoSrc = $featured && $videoId !== null ? youtube_muted_embed_url($videoId, 30, 40) : '';
    $minutes = reading_time_minutes($body);
    $section = section_normalize((string) ($post['section'] ?? 'news'));
    $sectionIcon = $section === 'article' ? 'articles' : 'news';
    $tags = is_array($post['tags'] ?? null) ? $post['tags'] : [];
    $classes = trim('card' . ($featured ? ' featured' : '') . ($extraClass !== '' ? ' ' . $extraClass : ''));

    ob_start();
    ?>
                <article class="<?= e($classes) ?>">
                    <h3 class="card-heading">
                        <?php if (!$featured): ?>
                            <span class="card-heading__icon" aria-hidden="true"><?= nav_lucide_icon($sectionIcon, 16) ?></span>
                        <?php endif; ?>
                        <a href="<?= e($url) ?>"><?= e($title) ?></a>
                    </h3>
                    <a href="<?= e($url) ?>" class="card-thumb"<?= $carouselVideoSrc !== '' ? ' data-carousel-video="' . e($carouselVideoSrc) . '"' : '' ?>>
                        <?php if ($thumb !== null): ?>
                            <img src="<?= e($thumb) ?>" alt="" loading="<?= $featured ? 'eager' : 'lazy' ?>">
                        <?php else: ?>
                            <span class="ph" aria-hidden="true">🎮</span>
                        <?php endif; ?>
                        <?php if ($carouselVideoSrc !== ''): ?>
                            <span class="card-thumb__video" aria-hidden="true"></span>
                        <?php endif; ?>
                    </a>
                    <div class="card-body">
                        <?php if ($excerpt !== ''): ?>
                            <p class="excerpt"><?= e($excerpt) ?></p>
                        <?php endif; ?>
                        <?php if ($tags !== []): ?>
                            <div class="tag-row">
                                <?php foreach ($tags as $item): ?>
                                    <a class="tag" href="<?= e(site_tag_url($config, (string) $item['slug'])) ?>">#<?= e((string) $item['name']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-meta">
                        <span class="cat"><?= e(section_label($section)) ?></span>
                        <div class="card-meta__info">
                            <?php if ($date !== ''): ?>
                                <time datetime="<?= e((string) $post['published_at']) ?>"><?= e($date) ?></time>
                            <?php endif; ?>
                            <span class="dot" aria-hidden="true"></span>
                            <span><?= e(reading_time_label($minutes)) ?></span>
                        </div>
                    </div>
                </article>
    <?php

    return (string) ob_get_clean();
}

/**
 * Card de evento do calendário para a home (carrossel ou abaixo).
 *
 * @param array<string, mixed> $event
 */
function render_home_event_card(array $config, array $event, bool $featured = false, string $extraClass = ''): string
{
    $titleName = trim((string) ($event['title'] ?? ''));
    $title = calendar_event_display_title(
        $titleName !== '' ? $titleName : 'Xbox',
        (string) ($event['starts_at'] ?? 'now')
    );
    $body = (string) ($event['body'] ?? '');
    $excerpt = excerpt_from_html($body, $featured ? 180 : 120);
    $banner = trim((string) ($event['banner_url'] ?? ''));
    $calendarUrl = rtrim((string) ($config['site_base_url'] ?? ''), '/') . '/calendario#eventos';
    $pill = t('home.event_pill');
    $more = t('home.event_more');
    $classes = trim(
        'card card--event'
        . ($featured ? ' featured' : '')
        . ($extraClass !== '' ? ' ' . $extraClass : '')
    );

    ob_start();
    ?>
                <article class="<?= e($classes) ?>">
                    <a href="<?= e($calendarUrl) ?>" class="card-thumb">
                        <?php if ($banner !== ''): ?>
                            <img src="<?= e($banner) ?>" alt="" loading="<?= $featured ? 'eager' : 'lazy' ?>">
                        <?php else: ?>
                            <span class="ph" aria-hidden="true">📅</span>
                        <?php endif; ?>
                        <span class="cat cat--events"><?= e($pill) ?></span>
                    </a>
                    <div class="card-body">
                        <h3><a href="<?= e($calendarUrl) ?>"><?= e($title) ?></a></h3>
                        <?php if ($excerpt !== ''): ?>
                            <p class="excerpt"><?= e($excerpt) ?>
                                <a class="card-more" href="<?= e($calendarUrl) ?>"><?= e($more) ?></a>
                            </p>
                        <?php else: ?>
                            <p class="excerpt">
                                <a class="card-more" href="<?= e($calendarUrl) ?>"><?= e($more) ?></a>
                            </p>
                        <?php endif; ?>
                    </div>
                </article>
    <?php

    return (string) ob_get_clean();
}

/**
 * Card de fato histórico para o carrossel da home (só no dia do aniversário).
 *
 * @param array<string, mixed> $item
 */
function render_home_history_card(array $config, array $item, bool $featured = false, string $extraClass = ''): string
{
    $title = calendar_history_headline($item);
    $body = (string) ($item['body'] ?? '');
    $excerpt = excerpt_from_html($body, $featured ? 180 : 120);
    $cover = trim((string) ($item['cover_url'] ?? ''));
    $calendarUrl = rtrim((string) ($config['site_base_url'] ?? ''), '/') . '/calendario#historico';
    $pill = t('home.history_pill');
    $more = t('home.event_more');
    $classes = trim(
        'card card--history'
        . ($featured ? ' featured' : '')
        . ($extraClass !== '' ? ' ' . $extraClass : '')
    );

    ob_start();
    ?>
                <article class="<?= e($classes) ?>">
                    <a href="<?= e($calendarUrl) ?>" class="card-thumb">
                        <?php if ($cover !== ''): ?>
                            <img src="<?= e($cover) ?>" alt="" loading="<?= $featured ? 'eager' : 'lazy' ?>">
                        <?php else: ?>
                            <span class="ph" aria-hidden="true">📜</span>
                        <?php endif; ?>
                        <span class="cat cat--history"><?= e($pill) ?></span>
                    </a>
                    <div class="card-body">
                        <h3><a href="<?= e($calendarUrl) ?>"><?= e($title) ?></a></h3>
                        <?php if ($excerpt !== ''): ?>
                            <p class="excerpt"><?= e($excerpt) ?>
                                <a class="card-more" href="<?= e($calendarUrl) ?>"><?= e($more) ?></a>
                            </p>
                        <?php else: ?>
                            <p class="excerpt">
                                <a class="card-more" href="<?= e($calendarUrl) ?>"><?= e($more) ?></a>
                            </p>
                        <?php endif; ?>
                    </div>
                </article>
    <?php

    return (string) ob_get_clean();
}

/**
 * Arte "Chegando Hoje" (gerada pelo Xbox Manager) com os lançamentos do dia.
 *
 * @param array{image_url: string, target: string, titles: list<string>} $slide
 */
function render_home_today_card(array $config, array $slide, bool $featured = false, string $extraClass = ''): string
{
    $calendarUrl = rtrim((string) ($config['site_base_url'] ?? ''), '/') . '/calendario';
    $title = t($slide['target'] === 'gamepass' ? 'home.today_releases_gp' : 'home.today_releases_xbox');
    $games = implode(' • ', $slide['titles']);
    $classes = trim(
        'card card--today'
        . ($featured ? ' featured' : '')
        . ($extraClass !== '' ? ' ' . $extraClass : '')
    );

    ob_start();
    ?>
                <article class="<?= e($classes) ?>">
                    <a href="<?= e($calendarUrl) ?>" class="card-thumb">
                        <img src="<?= e($slide['image_url']) ?>" alt="<?= e($title) ?>" loading="<?= $featured ? 'eager' : 'lazy' ?>">
                        <span class="cat cat--calendar"><?= e(t('home.calendar_pill')) ?></span>
                    </a>
                    <div class="card-body">
                        <h3><a href="<?= e($calendarUrl) ?>"><?= e($title) ?></a></h3>
                        <p class="excerpt"><?= e($games) ?>
                            <a class="card-more" href="<?= e($calendarUrl) ?>"><?= e(t('home.event_more')) ?></a>
                        </p>
                    </div>
                </article>
    <?php

    return (string) ob_get_clean();
}

/**
 * @param list<array<string, mixed>> $posts
 * @param list<array<string, mixed>> $eventSlides
 * @param list<array<string, mixed>> $historySlides
 * @param array<string, mixed>|null $todaySlide
 */
function render_news_carousel(
    array $config,
    array $posts,
    array $eventSlides = [],
    array $historySlides = [],
    ?array $todaySlide = null
): string
{
    $slides = [];
    if ($todaySlide !== null) {
        $slides[] = ['kind' => 'today', 'data' => $todaySlide];
    }
    foreach ($eventSlides as $event) {
        $slides[] = ['kind' => 'event', 'data' => $event];
    }
    foreach ($historySlides as $history) {
        $slides[] = ['kind' => 'history', 'data' => $history];
    }
    foreach ($posts as $post) {
        $slides[] = ['kind' => 'post', 'data' => $post];
    }

    if ($slides === []) {
        return '';
    }

    $count = count($slides);
    ob_start();
    ?>
            <div class="home-featured" aria-label="<?= e(t('ui.featured')) ?>">
                <div class="home-featured__head">
                    <span class="home-featured__badge"><?= e(t('ui.featured')) ?></span>
                </div>
                <div class="news-carousel" data-news-carousel<?= $count > 1 ? '' : ' data-single="1"' ?>>
                <div class="news-carousel__viewport">
                    <?php foreach ($slides as $index => $slide): ?>
                        <?php
                        $slideClass = 'news-carousel__slide' . ($index === 0 ? ' is-active' : '');
                        $kind = (string) ($slide['kind'] ?? 'post');
                        if ($kind === 'today') {
                            echo render_home_today_card($config, $slide['data'], true, $slideClass);
                        } elseif ($kind === 'event') {
                            echo render_home_event_card($config, $slide['data'], true, $slideClass);
                        } elseif ($kind === 'history') {
                            echo render_home_history_card($config, $slide['data'], true, $slideClass);
                        } else {
                            echo render_post_card($config, $slide['data'], true, $slideClass);
                        }
                        ?>
                    <?php endforeach; ?>
                </div>
                <?php if ($count > 1): ?>
                    <div class="news-carousel__dots" role="tablist" aria-label="<?= e(t('ui.featured')) ?>">
                        <?php for ($i = 0; $i < $count; ++$i): ?>
                            <button
                                type="button"
                                class="news-carousel__dot<?= $i === 0 ? ' is-active' : '' ?>"
                                role="tab"
                                aria-selected="<?= $i === 0 ? 'true' : 'false' ?>"
                                aria-label="<?= e(t('ui.show_story', ['n' => (string) ($i + 1), 'total' => (string) $count])) ?>"
                                data-carousel-index="<?= e((string) $i) ?>"
                            ></button>
                        <?php endfor; ?>
                    </div>
                <?php endif; ?>
                </div>
            </div>
    <?php

    return (string) ob_get_clean();
}

function render_home_content(
    array $config,
    array $posts,
    int $page,
    int $totalPages,
    ?string $heading = null,
    ?string $lead = null,
    ?string $secao = null,
    ?string $tag = null,
    ?PDO $pdo = null
): string
{
    $heading = $heading ?? t('home.latest');
    $lead = $lead ?? t('home.lead');
    $carouselPosts = [];
    $listPosts = $posts;
    $eventCarousel = [];
    $historyCarousel = [];
    $todaySlide = null;
    $isMainHome = $secao === null && $tag === null;
    $isNewsList = $tag === null && ($secao === null || $secao === 'noticias');
    $postGridClass = $isNewsList ? 'post-grid post-grid--news' : 'post-grid';

    if ($isMainHome && $page === 1 && $posts !== []) {
        $carouselPosts = array_slice($posts, 0, FEATURED_CAROUSEL_COUNT);
        $listPosts = array_slice($posts, count($carouselPosts));
    }

    if ($isMainHome && $page === 1 && $pdo instanceof PDO) {
        $eventCarousel = calendar_home_events($pdo)['carousel'];
        $historyCarousel = calendar_home_history($pdo);
        $todaySlide = home_today_slide_for_today($pdo);
    }
    $hasCarousel = $carouselPosts !== [] || $eventCarousel !== [] || $historyCarousel !== [] || $todaySlide !== null;

    ob_start();
    ?>
<section>
    <?php if (!$isMainHome): ?>
    <div class="section-head">
        <h2><?= e($heading) ?></h2>
        <p><?= e($lead) ?></p>
    </div>
    <?php endif; ?>

    <div class="<?= e($postGridClass) ?>">
        <?php if ($posts === [] && !$hasCarousel): ?>
            <div class="empty">
                <div class="icon" aria-hidden="true">🎮</div>
                <p><?= e(t('ui.empty_section')) ?></p>
            </div>
        <?php else: ?>
            <?php if ($hasCarousel): ?>
                <?= render_news_carousel($config, $carouselPosts, $eventCarousel, $historyCarousel, $todaySlide) ?>
                <?= render_ad_slot('in_feed') ?>
            <?php endif; ?>

            <?php foreach ($listPosts as $post): ?>
                <?= render_post_card($config, $post, false) ?>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($totalPages > 1): ?>
            <?= render_pagination_nav(
                $page,
                $totalPages,
                static fn (int $p): string => site_list_url($config, $p, $secao, $tag)
            ) ?>
        <?php endif; ?>
    </div>
</section>
    <?php

    return (string) ob_get_clean();
}

function render_article_content(array $post, array $config): string
{
    $title = (string) $post['title'];
    $featured = extract_featured_image((string) $post['body']);
    $date = format_post_date(isset($post['published_at']) ? (string) $post['published_at'] : null);
    $minutes = reading_time_minutes($featured['body']);
    $heroSrc = $featured['src'] ?? default_share_image_path();
    $usingDefaultHero = $featured['src'] === null;
    $heroAlt = $featured['alt'] !== '' ? $featured['alt'] : $title;
    $heroVideoId = youtube_video_id(isset($post['featured_video_url']) ? (string) $post['featured_video_url'] : null);
    $section = section_normalize((string) ($post['section'] ?? 'news'));
    $tags = is_array($post['tags'] ?? null) ? $post['tags'] : [];
    $backUrl = site_section_url($config, $section);
    $backLabel = $section === 'article' ? t('ui.back_articles') : t('ui.back_news');

    ob_start();
    ?>
    <div>
        <a class="back-link" href="<?= e($backUrl) ?>">‹ <?= e($backLabel) ?></a>
        <article class="article">
            <div class="article-hero<?= $heroVideoId !== null ? ' article-hero--video' : '' ?>">
                <?php if ($heroVideoId !== null): ?>
                    <iframe
                        class="article-hero__video"
                        src="<?= e(youtube_muted_embed_url($heroVideoId, null, null, true)) ?>"
                        title="<?= e($title) ?>"
                        allow="autoplay; encrypted-media; picture-in-picture"
                        allowfullscreen
                        referrerpolicy="strict-origin-when-cross-origin"
                    ></iframe>
                <?php else: ?>
                    <img src="<?= e($heroSrc) ?>" alt="<?= e($heroAlt) ?>"<?= $usingDefaultHero ? ' class="article-hero__default"' : '' ?>>
                <?php endif; ?>
                <a class="cat" href="<?= e(site_section_url($config, $section)) ?>"><?= e(section_label($section)) ?></a>
            </div>
            <div class="article-inner">
                <h1><?= e($title) ?></h1>
                <div class="article-meta">
                    <?php if ($date !== ''): ?>
                        <span>📅 <?= e($date) ?></span>
                    <?php endif; ?>
                    <span>⏱️ <?= e(reading_time_label($minutes)) ?></span>
                    <?= render_share_menu($config, $post) ?>
                </div>
                <div class="article-content">
                    <?= $featured['body'] ?>
                </div>
                <?php if ($tags !== []): ?>
                    <div class="tag-row tag-row--article">
                        <?php foreach ($tags as $item): ?>
                            <a class="tag" href="<?= e(site_tag_url($config, (string) $item['slug'])) ?>">#<?= e((string) $item['name']) ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </article>
        <?= render_ad_slot('in_article') ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

function render_not_found_content(): string
{
    ob_start();
    ?>
<div class="empty" style="margin:12px 0">
    <div class="icon" aria-hidden="true">🔍</div>
    <p><?= e(t('ui.not_found_body')) ?></p>
    <a class="btn btn-brand" style="margin-top:16px" href="/"><?= e(t('ui.back_home')) ?></a>
</div>
    <?php

    return (string) ob_get_clean();
}

/**
 * @param list<array<string, mixed>> $posts
 * @param list<array{type: string, title: string, excerpt: string, when: string, url: string}> $calendarHits
 */
function render_search_content(
    array $config,
    string $q,
    array $posts,
    int $page,
    int $totalPages,
    array $calendarHits = [],
    bool $tooShort = false
): string {
    $typeLabels = [
        'gamepass' => 'Game Pass',
        'event' => t('cal.events'),
        'history' => t('cal.history'),
    ];

    ob_start();
    ?>
<section>
    <div class="section-head">
        <h2><?= $q !== '' ? e(t('ui.search_results', ['q' => $q])) : e(t('ui.search_heading')) ?></h2>
        <p>
            <?php if ($q === ''): ?>
                <?= e(t('ui.search_lead')) ?>
            <?php elseif ($tooShort): ?>
                <?= e(t('ui.search_lead')) ?>
            <?php else: ?>
                <?= e(t('ui.search_lead')) ?>
            <?php endif; ?>
        </p>
    </div>

    <form class="search-page-form" action="/busca" method="get" role="search">
        <?php if (current_lang() !== 'pt'): ?>
            <input type="hidden" name="lang" value="<?= e(current_lang()) ?>">
        <?php endif; ?>
        <label class="visually-hidden" for="search-page-q"><?= e(t('ui.search')) ?></label>
        <span class="search-page-form__icon" aria-hidden="true"><?= nav_lucide_icon('search', 18) ?></span>
        <input id="search-page-q" type="search" name="q" value="<?= e($q) ?>" placeholder="<?= e(t('ui.search_placeholder')) ?>" minlength="2" autocomplete="off">
        <button type="submit" class="btn btn-brand"><?= e(t('ui.search_heading')) ?></button>
    </form>

    <?php if ($calendarHits !== []): ?>
        <div class="search-calendar">
            <h3 class="search-calendar__title"><?= calendar_lucide_icon('event', 18) ?> <?= e(t('cal.search_in')) ?></h3>
            <ul class="search-calendar__list">
                <?php foreach ($calendarHits as $hit): ?>
                    <?php
                    $type = (string) ($hit['type'] ?? 'event');
                    $label = $typeLabels[$type] ?? t('cal.title');
                    $isExternal = str_starts_with((string) $hit['url'], 'http');
                    ?>
                    <li>
                        <a href="<?= e((string) $hit['url']) ?>"<?= $isExternal ? ' rel="noopener noreferrer" target="_blank"' : '' ?>>
                            <span class="search-calendar__type"><?= e($label) ?></span>
                            <span class="search-calendar__name"><?= e((string) $hit['title']) ?></span>
                            <?php if (($hit['when'] ?? '') !== ''): ?>
                                <time><?= e((string) $hit['when']) ?></time>
                            <?php endif; ?>
                        </a>
                        <?php if (($hit['excerpt'] ?? '') !== ''): ?>
                            <p><?= e((string) $hit['excerpt']) ?></p>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
            <a class="search-calendar__more" href="/calendario"><?= e(t('cal.search_more')) ?></a>
        </div>
    <?php endif; ?>

    <div class="post-grid">
        <?php if ($q !== '' && !$tooShort && $posts === [] && $calendarHits === []): ?>
            <div class="empty">
                <div class="icon" aria-hidden="true">🔍</div>
                <p><?= e(t('ui.no_results', ['q' => $q])) ?></p>
            </div>
        <?php elseif ($posts !== []): ?>
            <?php foreach ($posts as $index => $post): ?>
                <?php
                $postId = (int) $post['id'];
                $title = (string) $post['title'];
                $body = (string) $post['body'];
                $url = site_post_url($config, $postId);
                $excerpt = excerpt_from_html($body, 160);
                $date = format_post_date(isset($post['published_at']) ? (string) $post['published_at'] : null);
                $thumb = first_image_src($body);
                $minutes = reading_time_minutes($body);
                $section = section_normalize((string) ($post['section'] ?? 'news'));
                $tags = is_array($post['tags'] ?? null) ? $post['tags'] : [];
                ?>
                <article class="card">
                    <a href="<?= e($url) ?>" class="card-thumb">
                        <?php if ($thumb !== null): ?>
                            <img src="<?= e($thumb) ?>" alt="" loading="lazy">
                        <?php else: ?>
                            <span class="ph" aria-hidden="true">🎮</span>
                        <?php endif; ?>
                        <span class="cat"><?= e(section_label($section)) ?></span>
                    </a>
                    <div class="card-body">
                        <h3><a href="<?= e($url) ?>"><?= e($title) ?></a></h3>
                        <?php if ($excerpt !== ''): ?>
                            <p class="excerpt"><?= e($excerpt) ?></p>
                        <?php endif; ?>
                        <?php if ($tags !== []): ?>
                            <div class="tag-row">
                                <?php foreach ($tags as $item): ?>
                                    <a class="tag" href="<?= e(site_tag_url($config, (string) $item['slug'])) ?>">#<?= e((string) $item['name']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <div class="card-meta">
                            <?php if ($date !== ''): ?>
                                <time datetime="<?= e((string) $post['published_at']) ?>"><?= e($date) ?></time>
                            <?php endif; ?>
                            <span class="dot" aria-hidden="true"></span>
                            <span><?= e(reading_time_label($minutes)) ?></span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($totalPages > 1 && $posts !== []): ?>
            <?= render_pagination_nav(
                $page,
                $totalPages,
                static fn (int $p): string => site_search_url($config, $q, $p)
            ) ?>
        <?php endif; ?>
    </div>
</section>
    <?php

    return (string) ob_get_clean();
}