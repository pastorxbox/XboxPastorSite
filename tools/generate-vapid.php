<?php

declare(strict_types=1);

/**
 * Gera par VAPID para Web Push.
 * Uso: docker compose exec web php /var/www/tools/generate-vapid.php
 *   ou: php tools/generate-vapid.php (com vendor)
 */

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "vendor/autoload.php em falta. Corre composer install.\n");
    exit(1);
}

require $autoload;

use Minishlink\WebPush\VAPID;

$keys = VAPID::createVapidKeys();
echo "VAPID_PUBLIC_KEY=" . $keys['publicKey'] . PHP_EOL;
echo "VAPID_PRIVATE_KEY=" . $keys['privateKey'] . PHP_EOL;
echo "VAPID_SUBJECT=mailto:admin@pastorxbox.com.br" . PHP_EOL;
