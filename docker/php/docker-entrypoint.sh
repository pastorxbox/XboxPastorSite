#!/bin/bash
set -euo pipefail

mkdir -p /var/www/html/uploads
chown -R www-data:www-data /var/www/html/uploads 2>/dev/null || true

exec apache2-foreground
