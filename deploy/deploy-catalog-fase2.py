#!/usr/bin/env python3
"""Fase 2 — Catálogo Xbox: SQL + endpoints API no Hostinger."""
import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ.get("SSHPASS")
if not PASSWORD:
    print("SSHPASS env var required", file=sys.stderr)
    sys.exit(1)

files = [
    ("public/.htaccess", f"{REMOTE}/.htaccess"),
    ("public/api/index.php", f"{REMOTE}/api/index.php"),
    ("public/includes/catalog.php", f"{REMOTE}/includes/catalog.php"),
    ("public/includes/api-catalog.php", f"{REMOTE}/includes/api-catalog.php"),
    ("sql/022_catalog_fase2.sql", "domains/pastorxbox.com.br/sql/022_catalog_fase2.sql"),
]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "147.93.39.20",
    port=65002,
    username="u285071835",
    password=PASSWORD,
    allow_agent=False,
    look_for_keys=False,
    timeout=45,
)
sftp = c.open_sftp()
try:
    sftp.stat("domains/pastorxbox.com.br/sql")
except OSError:
    try:
        sftp.mkdir("domains/pastorxbox.com.br/sql")
    except OSError:
        pass

for local, remote in files:
    path = ROOT / local
    if not path.is_file():
        print(f"MISSING {path}")
        sftp.close()
        c.close()
        sys.exit(1)
    print(f"put {path.name} -> {remote}")
    sftp.put(str(path), remote)
sftp.close()

migrate = r"""
set -e
export $(grep -E '^(DB_HOST|DB_NAME|DB_USER|DB_PASS|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=' domains/pastorxbox.com.br/config/.env 2>/dev/null | xargs) || true
DBN="${DB_NAME:-$DB_DATABASE}"
DBU="${DB_USER:-$DB_USERNAME}"
DBP="${DB_PASS:-$DB_PASSWORD}"
DBH="${DB_HOST:-localhost}"
echo "Migrating $DBN @ $DBH as $DBU"
SQL=domains/pastorxbox.com.br/sql/022_catalog_fase2.sql

# Tabelas (CREATE IF NOT EXISTS)
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "
CREATE TABLE IF NOT EXISTS catalog_games (
  id VARCHAR(64) NOT NULL,
  manager_game_id VARCHAR(64) NOT NULL,
  slug VARCHAR(190) NOT NULL,
  title VARCHAR(255) NOT NULL,
  synopsis MEDIUMTEXT NULL,
  cover_url VARCHAR(1024) NULL,
  status VARCHAR(32) NOT NULL DEFAULT 'DRAFT',
  franchise_name VARCHAR(255) NULL,
  franchise_slug VARCHAR(190) NULL,
  synced_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_games_manager (manager_game_id),
  UNIQUE KEY uq_catalog_games_slug (slug),
  KEY idx_catalog_games_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS catalog_store_products (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  catalog_game_id VARCHAR(64) NOT NULL,
  store_product_id VARCHAR(64) NOT NULL,
  store_url VARCHAR(1024) NULL,
  edition_name VARCHAR(255) NULL,
  product_type VARCHAR(32) NOT NULL DEFAULT 'UNKNOWN',
  market VARCHAR(8) NOT NULL DEFAULT 'BR',
  currency VARCHAR(8) NOT NULL DEFAULT 'BRL',
  active TINYINT(1) NOT NULL DEFAULT 1,
  price_current DECIMAL(12,2) NULL,
  price_reference DECIMAL(12,2) NULL,
  price_status VARCHAR(32) NULL,
  price_checked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_catalog_product_store (store_product_id, market),
  KEY idx_catalog_product_game (catalog_game_id),
  CONSTRAINT fk_catalog_product_game
    FOREIGN KEY (catalog_game_id) REFERENCES catalog_games (id)
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS catalog_price_mirror (
  store_product_id VARCHAR(64) NOT NULL,
  market VARCHAR(8) NOT NULL DEFAULT 'BR',
  current_price DECIMAL(12,2) NULL,
  reference_price DECIMAL(12,2) NULL,
  currency VARCHAR(8) NOT NULL DEFAULT 'BRL',
  status VARCHAR(32) NULL,
  checked_at DATETIME NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (store_product_id, market)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
"
echo "tables ok"

HAS=$(mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -N -e "SHOW COLUMNS FROM gp_releases LIKE 'catalog_game_id';" | wc -l)
if [ "$HAS" = "0" ]; then
  mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "ALTER TABLE gp_releases ADD COLUMN catalog_game_id VARCHAR(64) NULL AFTER price_brl;"
  echo "column catalog_game_id added"
else
  echo "column catalog_game_id already present"
fi

IDX=$(mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -N -e "SHOW INDEX FROM gp_releases WHERE Key_name='idx_gp_releases_catalog_game';" | wc -l)
if [ "$IDX" = "0" ]; then
  mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "ALTER TABLE gp_releases ADD KEY idx_gp_releases_catalog_game (catalog_game_id);"
  echo "index added"
else
  echo "index already present"
fi

mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "SHOW TABLES LIKE 'catalog_%'; SHOW COLUMNS FROM gp_releases LIKE 'catalog_game_id';"
echo "SQL file present: $SQL"
"""
_, o, e = c.exec_command(migrate, timeout=120)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    # mysql -p often writes password warning to stderr
    print("STDERR:", err[:800])

smoke = r"""
set -e
# Sem Bearer → 401 (rota existe)
CODE=$(curl -sL -o /tmp/catalog_smoke.json -w '%{http_code}' -X PUT \
  -H 'Content-Type: application/json' \
  -d '{"manager_game_id":"smoke","slug":"smoke","title":"Smoke","do_not_overwrite_price_brl":true,"products":[]}' \
  'https://pastorxbox.com.br/api/catalog/games/smoke')
echo "PUT catalog/games without auth: $CODE"
head -c 200 /tmp/catalog_smoke.json; echo
CODE2=$(curl -sL -o /tmp/catalog_link_smoke.json -w '%{http_code}' -X PUT \
  -H 'Content-Type: application/json' \
  -d '{"catalog_game_id":null}' \
  'https://pastorxbox.com.br/api/calendar/gamepass/1/catalog-link')
echo "PUT catalog-link without auth: $CODE2"
head -c 200 /tmp/catalog_link_smoke.json; echo
grep -n 'api-catalog\|catalog/games\|catalog-link' \
  domains/pastorxbox.com.br/public_html/api/index.php | head -20
test -f domains/pastorxbox.com.br/public_html/includes/catalog.php && echo 'catalog.php OK'
test -f domains/pastorxbox.com.br/public_html/includes/api-catalog.php && echo 'api-catalog.php OK'
"""
_, o, e = c.exec_command(smoke, timeout=60)
print("SMOKE:")
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("SMOKE ERR:", err[:400])
c.close()
print("DONE")
