<?php

declare(strict_types=1);

/**
 * Layout da vitrine (Fase 2). Reutilizado nas páginas públicas.
 */
function render_vitrine_page(array $config, string $pageTitle, string $mainHtml): void
{
    $siteName = htmlspecialchars((string) ($config['site_name'] ?? 'Pastor Xbox'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $title = htmlspecialchars($pageTitle, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    $xScreenName = preg_replace('/[^A-Za-z0-9_]/', '', (string) ($config['x_screen_name'] ?? 'PastorXbox')) ?: 'PastorXbox';
    $xProfileUrl = 'https://x.com/' . rawurlencode($xScreenName);
    $gaMeasurementId = trim((string) ($config['ga_measurement_id'] ?? ''));
    $assetVersion = '20260824';

    header('Content-Type: text/html; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Cache-Control: no-store');
    ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $title ?> — <?= $siteName ?></title>
    <link rel="stylesheet" href="/assets/css/vitrine.css?v=<?= $assetVersion ?>">
</head>
<body data-ga-id="<?= htmlspecialchars($gaMeasurementId, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
    <header class="site-header">
        <div class="site-header__inner">
            <a class="site-brand" href="/">
                <span class="site-brand__mark" aria-hidden="true">PX</span>
                <span>
                    <span class="site-brand__name"><?= $siteName ?></span>
                    <span class="site-brand__tagline">Notícias Xbox</span>
                </span>
            </a>
        </div>
        <div id="ad-header" class="ad-slot" aria-label="Espaço reservado para anúncio no cabeçalho">
            <p class="ad-slot__label">Anúncio — cabeçalho</p>
        </div>
    </header>

    <div class="page-wrap">
        <main class="content">
            <?= $mainHtml ?>
            <div id="ad-in-article" class="ad-slot" aria-label="Espaço reservado para anúncio no artigo">
                <p class="ad-slot__label">Anúncio — no artigo</p>
            </div>
        </main>

        <aside class="sidebar" aria-label="Barra lateral">
            <div id="ad-sidebar-top" class="ad-slot" aria-label="Espaço reservado para anúncio lateral superior">
                <p class="ad-slot__label">Anúncio — lateral superior</p>
            </div>

            <section class="x-panel" aria-labelledby="x-panel-title">
                <div class="x-panel__header" id="x-panel-title">@<?= htmlspecialchars($xScreenName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?> no X</div>
                <div class="x-panel__body">
                    <div id="x-timeline-embed" class="x-panel__embed">
                        <a
                            class="twitter-timeline"
                            data-height="420"
                            data-theme="dark"
                            href="<?= htmlspecialchars($xProfileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>?ref_src=twsrc%5Etfw"
                        >Tweets de @<?= htmlspecialchars($xScreenName, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
                    </div>
                    <div id="x-timeline-fallback" class="x-panel__fallback">
                        <p>Não foi possível carregar a timeline do X neste momento.</p>
                        <a class="x-panel__link" href="<?= htmlspecialchars($xProfileUrl, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>" rel="noopener noreferrer" target="_blank">
                            Ver no X
                        </a>
                    </div>
                </div>
            </section>

            <div id="ad-sidebar-bottom" class="ad-slot" aria-label="Espaço reservado para anúncio lateral inferior">
                <p class="ad-slot__label">Anúncio — lateral inferior</p>
            </div>
        </aside>
    </div>

    <footer class="site-footer">
        <p>&copy; <?= date('Y') ?> <?= $siteName ?>. Todos os direitos reservados.</p>
    </footer>

    <div id="cookie-notice" class="cookie-notice" role="dialog" aria-live="polite" aria-label="Aviso de cookies">
        <div class="cookie-notice__inner">
            <p class="cookie-notice__text">
                Usamos cookies para medir audiência<?= $gaMeasurementId !== '' ? ' (Google Analytics)' : '' ?>.
                Ao continuar, você concorda com o uso de cookies conforme nossa política de privacidade.
            </p>
            <div class="cookie-notice__actions">
                <button type="button" class="btn btn--primary" id="cookie-accept">Aceitar</button>
            </div>
        </div>
    </div>

    <script async src="https://platform.twitter.com/widgets.js" charset="utf-8"></script>
    <script src="/assets/js/vitrine.js?v=<?= $assetVersion ?>" defer></script>
</body>
</html>
    <?php
}
