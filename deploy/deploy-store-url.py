#!/usr/bin/env python3
"""Run store_url migration and upload release-card files to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/includes/helpers.php", f"{REMOTE}/includes/helpers.php"),
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/api-calendar.php", f"{REMOTE}/includes/api-calendar.php"),
    ("sql/008_releases_store_url.sql", "domains/pastorxbox.com.br/sql/008_releases_store_url.sql"),
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
        print(f"SKIP missing {path}")
        continue
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
# Idempotent: skip if column already exists
COL=$(mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -N -e "SHOW COLUMNS FROM gp_releases LIKE 'store_url';" | wc -l)
if [ "$COL" = "0" ]; then
  mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" < domains/pastorxbox.com.br/sql/008_releases_store_url.sql
  echo "Column store_url added"
else
  echo "Column store_url already present"
fi
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "SHOW COLUMNS FROM gp_releases LIKE 'store_url';"
"""
_, o, e = c.exec_command(migrate, timeout=60)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:800])

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' "
    "| grep -oE 'format_release|cal-card__store|Chega em [0-9/]+( [0-9:]*)?' "
    "| head -20; "
    "grep -n 'format_release_date\\|cal-card__store\\|store_url' "
    "domains/pastorxbox.com.br/public_html/includes/helpers.php "
    "domains/pastorxbox.com.br/public_html/includes/calendar.php "
    "domains/pastorxbox.com.br/public_html/assets/css/vitrine.css | head -20"
)
print("SMOKE:")
print(o.read().decode(errors="replace"))
c.close()
print("DONE")
