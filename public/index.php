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
$siteName = htmlspecialchars((string) ($config['site_name'] ?? 'Pastor Xbox'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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
    <title><?= $siteName ?></title>
    <style>
        :root { color-scheme: dark; }
        body {
            margin: 0;
            min-height: 100vh;
            font-family: system-ui, sans-serif;
            background: #0b0f14;
            color: #e8eef5;
            display: grid;
            place-items: center;
        }
        main {
            text-align: center;
            padding: 2rem;
        }
        h1 {
            margin: 0 0 0.5rem;
            font-size: 2rem;
            letter-spacing: 0.04em;
        }
        p { margin: 0; color: #9aa8b8; }
    </style>
</head>
<body>
    <main>
        <h1><?= $siteName ?></h1>
        <p>Site em construção. Conteúdo na próxima fase.</p>
    </main>
</body>
</html>
