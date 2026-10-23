#!/usr/bin/env python3
"""Run on_gamepass migration and upload release files to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/includes/helpers.php", f"{REMOTE}/includes/helpers.php"),
    ("public/includes/posts.php", f"{REMOTE}/includes/posts.php"),
    ("public/includes/tags.php", f"{REMOTE}/includes/tags.php"),
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/api-calendar.php", f"{REMOTE}/includes/api-calendar.php"),
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
    ("public/index.php", f"{REMOTE}/index.php"),
    ("sql/007_releases_on_gamepass.sql", f"{REMOTE}/../sql/007_releases_on_gamepass.sql"),
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

# Ensure remote sql dir exists under domain
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
    # Prefer public_html sibling sql folder; if 007 path fails, put under home tmp
    if local.startswith("sql/"):
        remote = f"domains/pastorxbox.com.br/sql/{path.name}"
    print(f"put {path.name} -> {remote}")
    sftp.put(str(path), remote)
sftp.close()

# Run migration via mysql CLI using .env from config
migrate = r"""
set -e
CFG=domains/pastorxbox.com.br/config/.env
if [ ! -f "$CFG" ]; then CFG=domains/pastorxbox.com.br/config/config.php; fi
# Extract DB creds from .env
export $(grep -E '^(DB_HOST|DB_NAME|DB_USER|DB_PASS|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=' domains/pastorxbox.com.br/config/.env 2>/dev/null | xargs) || true
DBN="${DB_NAME:-$DB_DATABASE}"
DBU="${DB_USER:-$DB_USERNAME}"
DBP="${DB_PASS:-$DB_PASSWORD}"
DBH="${DB_HOST:-localhost}"
echo "Migrating $DBN @ $DBH as $DBU"
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" < domains/pastorxbox.com.br/sql/007_releases_on_gamepass.sql
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "SHOW COLUMNS FROM gp_releases LIKE 'on_gamepass'; SELECT id,title,on_gamepass FROM gp_releases LIMIT 5;"
"""
_, o, e = c.exec_command(migrate, timeout=60)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:800])

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' "
    "| grep -oE 'Lançamentos Xbox|cal-card__gp-badge|data-gp-service|Fora do serviço|data-gamepass=\"1\"' "
    "| sort | uniq -c"
)
print("SMOKE:")
print(o.read().decode(errors="replace"))
c.close()
print("DONE")
