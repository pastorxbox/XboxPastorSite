<?php

declare(strict_types=1);

/**
 * Configuração fora do document root.
 *   Docker:    /var/www/config/config.php  (env vars vêm do container)
 *   Hostinger: ~/pastorxbox-config/config.php  (.env ao lado deste ficheiro)
 *
 * Não expor este ficheiro via URL.
 */

// Carregar .env se existir (Hostinger / produção sem Docker)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            if (!getenv($key)) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

return [
    'site_name' => 'Pastor Xbox',
    'site_base_url' => getenv('SITE_BASE_URL') ?: 'http://localhost:8080',
    'db' => [
        'host' => getenv('DB_HOST') ?: 'mysql',
        'name' => getenv('DB_NAME') ?: 'pastorxbox',
        'user' => getenv('DB_USER') ?: 'pastorxbox',
        'pass' => getenv('DB_PASSWORD') ?: '',
        'charset' => 'utf8mb4',
    ],
    'site_api_key' => getenv('SITE_API_KEY') ?: '',
    'ga_measurement_id' => getenv('GA_MEASUREMENT_ID') ?: '',
    'x_screen_name' => getenv('X_SCREEN_NAME') ?: 'PastorXbox',
    'wp_rest_base_url' => getenv('WP_REST_BASE_URL') ?: 'https://pastorxbox.com.br/wp-json/wp/v2',
    'vapid_public_key' => getenv('VAPID_PUBLIC_KEY') ?: '',
    'vapid_private_key' => getenv('VAPID_PRIVATE_KEY') ?: '',
    'vapid_subject' => getenv('VAPID_SUBJECT') ?: 'mailto:admin@pastorxbox.com.br',
];
