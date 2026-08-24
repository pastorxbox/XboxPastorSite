<?php

declare(strict_types=1);

$configFile = dirname(__DIR__, 1) . '/config/config.php';
if (!is_file($configFile)) {
    $configFile = '/var/www/config/config.php';
}
if (!is_file($configFile)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Configuração em falta.';
    exit;
}

$config = require $configFile;

require __DIR__ . '/includes/vitrine-layout.php';

$mainHtml = <<<'HTML'
<section class="content__placeholder">
    <h1>Pastor Xbox</h1>
    <p>Site em construção. A listagem de notícias e artigos chega na próxima fase.</p>
</section>
HTML;

render_vitrine_page($config, 'Início', $mainHtml);
