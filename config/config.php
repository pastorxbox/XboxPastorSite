<?php

declare(strict_types=1);

/**
 * Configuração fora do document root (/var/www/html).
 * Não expor este ficheiro via URL.
 */
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
];
