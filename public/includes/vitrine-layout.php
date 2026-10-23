<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/i18n.php';
require_once __DIR__ . '/tvxbox.php';

/**
 * Layout da vitrine. Reutilizado nas páginas públicas.
 *
 * @param array{
 *   canonical?: string,
 *   og_title?: string,
 *   og_description?: string,
 *   og_type?: string,
 *   og_url?: string,
 *   og_image?: string,
 *   nav?: string,
 * } $meta
 * @param list<array<string, mixed>> $recentPosts
 * @param list<array{slug: string, name: string, uses?: int}> $popularTags
 * @param list<array<string, mixed>> $calendarEvents
 */
function render_vitrine_page(
    array $config,
    string $pageTitle,
    string $mainHtml,
    array $meta = [],
    int $statusCode = 200,
    array $recentPosts = [],
    array $popularTags = [],
    array $calendarEvents = [],
    string $calendarTickerHtml = ''
): void {
    if ($statusCode !== 200) {
        http_response_code($statusCode);
    }

    $siteName = htmlspecialchars((string) ($config['site_name'] ?? 'Pastor Xbox'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $title = htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $xScreenName = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($config['x_screen_name'] ?? 'PastorXbox')) ?: 'PastorXbox';
    $xProfileUrl = 'https://x.com/' . rawurlencode($xScreenName);
    $gaMeasurementId = trim((string) ($config['ga_measurement_id'] ?? ''));
    $assetVersion = '20261009hometoday1';
    $siteBase = rtrim((string) ($config['site_base_url'] ?? ''), '/');
    $vapidPublicKey = htmlspecialchars(trim((string) ($config['vapid_public_key'] ?? '')), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $pdo = $GLOBALS['pdo'] ?? null;
    $tvVideos = $pdo instanceof PDO ? tvxbox_list_for_player($pdo) : [];
    $lang = current_lang();
    $htmlLang = current_html_lang();
    $homeLabel = $pdo instanceof PDO ? i18n_t($pdo, 'site.home', $lang) : 'Início';
    $newsLabel = $pdo instanceof PDO ? i18n_t($pdo, 'site.news', $lang) : 'Notícias';
    $articlesLabel = $pdo instanceof PDO ? i18n_t($pdo, 'site.articles', $lang) : 'Artigos';
    $calendarLabel = $pdo instanceof PDO ? i18n_t($pdo, 'site.calendar', $lang) : 'Calendário';
    $brandLogoSrc = htmlspecialchars(brand_logo_url($pdo instanceof PDO ? $pdo : null), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $currentPath = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/');
    $langOptions = site_language_options();
    $rssFeedUrl = htmlspecialchars($siteBase . '/rss.xml', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $rssCalendarUrl = htmlspecialchars($siteBase . '/calendario/rss.xml', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $canonical = isset($meta['canonical']) ? htmlspecialchars((string) $meta['canonical'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
    $ogTitle = isset($meta['og_title']) ? htmlspecialchars((string) $meta['og_title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $title;
    $ogDescription = isset($meta['og_description']) ? htmlspecialchars((string) $meta['og_description'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
    $ogType = isset($meta['og_type']) ? htmlspecialchars((string) $meta['og_type'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : 'website';
    $ogUrl = isset($meta['og_url']) ? htmlspecialchars((string) $meta['og_url'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : $canonical;
    $ogImage = isset($meta['og_image']) ? htmlspecialchars((string) $meta['og_image'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '';
    $navHome = ($meta['nav'] ?? 'home') === 'home';
    $navNews = ($meta['nav'] ?? '') === 'noticias';
    $navArticles = ($meta['nav'] ?? '') === 'artigos';
    $navCalendar = ($meta['nav'] ?? '') === 'calendario';
    $xHandle = htmlspecialchars($xScreenName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $xHref = htmlspecialchars($xProfileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store');
    ?>
<!DOCTYPE html>
<html lang="<?= e($htmlLang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?> — <?= $siteName ?></title>
    <?php if ($canonical !== ''): ?>
    <link rel="canonical" href="<?= $canonical ?>">
    <?php endif; ?>
    <meta property="og:site_name" content="<?= $siteName ?>">
    <meta property="og:title" content="<?= $ogTitle ?>">
    <?php if ($ogDescription !== ''): ?>
    <meta property="og:description" content="<?= $ogDescription ?>">
    <?php endif; ?>
    <meta property="og:type" content="<?= $ogType ?>">
    <?php if ($ogUrl !== ''): ?>
    <meta property="og:url" content="<?= $ogUrl ?>">
    <?php endif; ?>
    <?php if ($ogImage !== ''): ?>
    <meta property="og:image" content="<?= $ogImage ?>">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:image" content="<?= $ogImage ?>">
    <?php else: ?>
    <meta name="twitter:card" content="summary">
    <?php endif; ?>
    <meta name="twitter:title" content="<?= $ogTitle ?>">
    <?php if ($ogDescription !== ''): ?>
    <meta name="twitter:description" content="<?= $ogDescription ?>">
    <?php endif; ?>
    <link rel="alternate" type="application/rss+xml" title="<?= $siteName ?> — <?= e($newsLabel) ?> (RSS)" href="<?= $rssFeedUrl ?>">
    <link rel="alternate" type="application/rss+xml" title="<?= $siteName ?> — <?= e($calendarLabel) ?> (RSS)" href="<?= $rssCalendarUrl ?>">
    <link rel="icon" href="/favicon.ico?v=<?= $assetVersion ?>" sizes="any">
    <link rel="icon" type="image/png" href="<?= $brandLogoSrc ?>">
    <link rel="apple-touch-icon" href="/assets/img/pwa-icon-192.png?v=<?= $assetVersion ?>">
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#107c10">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="<?= $siteName ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/assets/css/vitrine.css?v=<?= $assetVersion ?>">
    <?php if ($gaMeasurementId !== ''): ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?= htmlspecialchars($gaMeasurementId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      gtag('config', <?= json_encode($gaMeasurementId, JSON_UNESCAPED_SLASHES) ?>);
    </script>
    <?php endif; ?>
</head>
<body<?= $gaMeasurementId !== '' ? ' data-ga-id="' . htmlspecialchars($gaMeasurementId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '"' : '' ?>>
    <header class="site-header">
        <div class="container header-inner">
            <a class="brand" href="/">
                <img class="brand-logo" src="<?= $brandLogoSrc ?>" alt="" width="44" height="44">
                <div class="brand-text">
                    <h1><?= $siteName ?></h1>
                    <span><?= e(t('brand.tagline')) ?></span>
                </div>
            </a>
            <form class="site-search" action="/busca" method="get" role="search" data-site-search>
                <?php if ($lang !== 'pt'): ?>
                    <input type="hidden" name="lang" value="<?= e($lang) ?>">
                <?php endif; ?>
                <label class="visually-hidden" for="site-search-q"><?= e(t('ui.search')) ?></label>
                <button type="button" class="site-search__toggle" aria-expanded="false" aria-controls="site-search-panel">
                    <?= nav_lucide_icon('search', 18) ?>
                    <span class="visually-hidden"><?= e(t('ui.open_search')) ?></span>
                </button>
                <div class="site-search__panel" id="site-search-panel">
                    <span class="site-search__icon" aria-hidden="true"><?= nav_lucide_icon('search', 16) ?></span>
                    <input id="site-search-q" class="site-search__input" type="search" name="q" placeholder="<?= e(t('ui.search_placeholder')) ?>" minlength="2" autocomplete="off" enterkeyhint="search">
                    <button type="submit" class="site-search__submit">
                        <?= nav_lucide_icon('search', 16) ?>
                        <span class="visually-hidden"><?= e(t('ui.search')) ?></span>
                    </button>
                </div>
            </form>
            <nav class="main-nav" aria-label="<?= e(t('ui.nav_main')) ?>">
                <a href="<?= e(site_home_url($config)) ?>" class="<?= $navHome ? 'active' : '' ?>" title="<?= e($homeLabel) ?>">
                    <?= nav_lucide_icon('home') ?><span class="nav-label"><?= e($homeLabel) ?></span>
                </a>
                <a href="<?= e(site_section_url($config, 'news')) ?>" class="<?= $navNews ? 'active' : '' ?>" title="<?= e($newsLabel) ?>">
                    <?= nav_lucide_icon('news') ?><span class="nav-label"><?= e($newsLabel) ?></span>
                </a>
                <a href="<?= e(site_section_url($config, 'article')) ?>" class="<?= $navArticles ? 'active' : '' ?>" title="<?= e($articlesLabel) ?>">
                    <?= nav_lucide_icon('articles') ?><span class="nav-label"><?= e($articlesLabel) ?></span>
                </a>
                <a href="<?= e(site_list_url($config, 1, 'calendario', null)) ?>" class="<?= $navCalendar ? 'active' : '' ?>" title="<?= e($calendarLabel) ?>">
                    <?= nav_lucide_icon('calendar') ?><span class="nav-label"><?= e($calendarLabel) ?></span>
                </a>
                <?php
                $currentLangOption = $langOptions[0];
                foreach ($langOptions as $option) {
                    if ($option['code'] === $lang) {
                        $currentLangOption = $option;
                        break;
                    }
                }
                ?>
                <div class="lang-switcher" data-lang-switcher>
                    <button
                        type="button"
                        class="lang-switcher__toggle"
                        aria-expanded="false"
                        aria-haspopup="true"
                        aria-controls="lang-switcher-menu"
                        title="<?= e($currentLangOption['label']) ?>"
                    >
                        <span class="lang-switcher__current" aria-hidden="true"><?= $currentLangOption['flag'] ?></span>
                        <span class="visually-hidden"><?= e($currentLangOption['label']) ?></span>
                    </button>
                    <div class="lang-switcher__menu" id="lang-switcher-menu" role="menu" hidden>
                        <?php foreach ($langOptions as $option): ?>
                            <?php
                            $query = $_GET;
                            $query['lang'] = $option['code'];
                            $href = $currentPath . '?' . http_build_query($query);
                            $isCurrent = $lang === $option['code'];
                            ?>
                            <a
                                href="<?= e($href) ?>"
                                class="lang-switcher__option<?= $isCurrent ? ' is-active' : '' ?>"
                                role="menuitem"
                                hreflang="<?= e($option['code']) ?>"
                                lang="<?= e($option['code']) ?>"
                                <?= $isCurrent ? 'aria-current="true"' : '' ?>
                            >
                                <span class="lang-switcher__option-flag" aria-hidden="true"><?= $option['flag'] ?></span>
                                <span class="lang-switcher__option-label"><?= e($option['label']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </nav>
        </div>
    </header>

    <div class="container">
        <?= render_ad_slot('header') ?>
    </div>

    <main class="container">
        <?php if ($calendarTickerHtml !== ''): ?>
            <?= $calendarTickerHtml ?>
        <?php endif; ?>
        <div class="layout">
            <?= $mainHtml ?>

            <aside class="sidebar" aria-label="<?= e(t('ui.sidebar')) ?>">
                <?= render_ad_slot('sidebar_top') ?>

                <?= render_tvxbox_sidebar($tvVideos) ?>

                <?php if ($navCalendar): ?>
                    <?= render_calendar_events_sidebar($calendarEvents) ?>
                <?php endif; ?>

                <div class="widget">
                    <div class="widget-head"><span class="bar"></span> <?= e(t('ui.recent')) ?></div>
                    <div class="widget-body">
                        <?php if ($recentPosts === []): ?>
                            <p style="color:var(--text-mute);font-size:14px"><?= e(t('ui.no_posts')) ?></p>
                        <?php else: ?>
                            <ul class="recent-list">
                                <?php foreach ($recentPosts as $recent): ?>
                                    <?php
                                    $recentId = (int) $recent['id'];
                                    $recentDate = format_post_date(isset($recent['published_at']) ? (string) $recent['published_at'] : null);
                                    ?>
                                    <li>
                                        <a href="<?= e(site_post_url($config, $recentId)) ?>"><?= e((string) $recent['title']) ?></a>
                                        <?php if ($recentDate !== ''): ?>
                                            <time datetime="<?= e((string) $recent['published_at']) ?>"><?= e($recentDate) ?></time>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="widget">
                    <div class="widget-head"><span class="bar"></span> <?= e(t('ui.hashtags')) ?></div>
                    <div class="widget-body">
                        <?php if ($popularTags === []): ?>
                            <p style="color:var(--text-mute);font-size:14px"><?= e(t('ui.no_hashtags')) ?></p>
                        <?php else: ?>
                            <div class="tag-row">
                                <?php foreach ($popularTags as $item): ?>
                                    <a class="tag" href="<?= e(site_tag_url($config, (string) $item['slug'])) ?>">#<?= e((string) $item['name']) ?></a>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="widget">
                    <div class="widget-head"><span class="bar"></span> @<?= $xHandle ?> <?= e(t('ui.on_x')) ?></div>
                    <div class="social-card">
                        <p class="msg"><?= e(t('ui.follow_x')) ?></p>
                        <a class="btn btn-brand" href="<?= $xHref ?>" rel="noopener noreferrer" target="_blank"><?= e(t('ui.see_on_x')) ?></a>
                    </div>
                </div>

                <?= render_ad_slot('sidebar_bottom') ?>
            </aside>
        </div>
    </main>

    <footer class="site-footer">
        <div class="container footer-inner">
            <a class="brand" href="/">
                <img class="brand-logo" src="<?= $brandLogoSrc ?>" alt="" width="38" height="38">
                <div class="brand-text">
                    <h1><?= $siteName ?></h1>
                    <span><?= e(t('brand.footer')) ?></span>
                </div>
            </a>
            <nav class="footer-links">
                <a href="<?= e(site_home_url($config)) ?>"><?= e($homeLabel) ?></a>
                <a href="<?= e(site_section_url($config, 'news')) ?>"><?= e($newsLabel) ?></a>
                <a href="<?= e(site_section_url($config, 'article')) ?>"><?= e($articlesLabel) ?></a>
                <a href="<?= e(site_list_url($config, 1, 'calendario', null)) ?>"><?= e($calendarLabel) ?></a>
                <a href="<?= $xHref ?>" rel="noopener noreferrer" target="_blank">@<?= $xHandle ?></a>
                <?php if ($vapidPublicKey !== ''): ?>
                <button type="button" class="footer-push-btn" data-push-toggle><?= e(t('push.enable')) ?></button>
                <?php endif; ?>
            </nav>
            <nav class="footer-rss" aria-label="Feeds RSS">
                <span class="footer-rss__label">RSS</span>
                <a href="/rss.xml" type="application/rss+xml"><?= e($newsLabel) ?></a>
                <a href="/calendario/rss.xml" type="application/rss+xml"><?= e($calendarLabel) ?></a>
            </nav>
        </div>
        <div class="copyright">&copy; <?= date('Y') ?> <?= $siteName ?>. <?= e(t('ui.copyright')) ?></div>
    </footer>

    <div id="cookie-notice" class="cookie-notice" role="dialog" aria-live="polite" aria-label="<?= e(t('ui.cookies_aria')) ?>">
        <div class="cookie-notice__inner">
            <p class="cookie-notice__text">
                <?= e(t('ui.cookies_text', [
                    'ga' => $gaMeasurementId !== '' ? ' (Google Analytics)' : '',
                ])) ?>
            </p>
            <div class="cookie-notice__actions">
                <button type="button" class="btn btn-brand" id="cookie-accept"><?= e(t('ui.accept')) ?></button>
            </div>
        </div>
    </div>

    <script src="/assets/js/vitrine.js?v=<?= $assetVersion ?>" defer></script>
    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
          navigator.serviceWorker.register('/sw.js', { scope: '/' }).catch(function () {});
        });
      }
      window.__PX_PUSH__ = {
        vapidPublicKey: <?= json_encode(trim((string) ($config['vapid_public_key'] ?? '')), JSON_UNESCAPED_SLASHES) ?>,
        labels: {
          enable: <?= json_encode(t('push.enable'), JSON_UNESCAPED_UNICODE) ?>,
          enabled: <?= json_encode(t('push.enabled'), JSON_UNESCAPED_UNICODE) ?>,
          denied: <?= json_encode(t('push.denied'), JSON_UNESCAPED_UNICODE) ?>,
          unsupported: <?= json_encode(t('push.unsupported'), JSON_UNESCAPED_UNICODE) ?>,
          error: <?= json_encode(t('push.error'), JSON_UNESCAPED_UNICODE) ?>
        }
      };
    </script>
</body>
</html>
    <?php
}