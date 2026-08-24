<?php

declare(strict_types=1);

/**
 * Aplica schema e seed SQL manualmente (Fase 3).
 * Uso local: docker compose exec web php /var/www/tools/init-db.php
 */
$configFile = '/var/www/config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Configuração em falta.\n");
    exit(1);
}

$config = require $configFile;

require '/var/www/html/includes/db.php';

$pdo = db_connect($config);
$sqlDir = '/var/www/sql';
$files = ['001_schema.sql', '002_seed.sql'];

foreach ($files as $file) {
    $path = $sqlDir . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Ficheiro SQL em falta: {$path}\n");
        exit(1);
    }

    $sql = file_get_contents($path);
    if ($sql === false) {
        fwrite(STDERR, "Não foi possível ler: {$path}\n");
        exit(1);
    }

    $pdo->exec($sql);
    echo "Aplicado: {$file}\n";
}

echo "Base de dados pronta.\n";
