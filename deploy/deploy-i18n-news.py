#!/usr/bin/env python3
"""Deploy i18n labels and news translations to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/.htaccess", f"{REMOTE}/.htaccess"),
    ("public/api/index.php", f"{REMOTE}/api/index.php"),
    ("public/index.php", f"{REMOTE}/index.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/includes/api-posts.php", f"{REMOTE}/includes/api-posts.php"),
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
    ("public/includes/helpers.php", f"{REMOTE}/includes/helpers.php"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/posts.php", f"{REMOTE}/includes/posts.php"),
    ("public/includes/tags.php", f"{REMOTE}/includes/tags.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("sql/010_i18n.sql", "domains/pastorxbox.com.br/sql/010_i18n.sql"),
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
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" < domains/pastorxbox.com.br/sql/010_i18n.sql
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "SHOW TABLES LIKE 'post_translations'; SHOW TABLES LIKE 'site_settings';"
"""
_, o, e = c.exec_command(migrate, timeout=120)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:1200])

smoke = (
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/noticias?lang=en' | "
    "grep -oE 'Home|News|Articles|Calendar|EN|ES|BR' | sort | uniq -c; "
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/noticias?lang=es' | "
    "grep -oE 'Inicio|Noticias|Artículos|Calendario|EN|ES|BR' | sort | uniq -c"
)
_, o, e = c.exec_command(smoke, timeout=60)
print("SMOKE:")
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("SMOKE_ERR:", err[:800])

c.close()
print("DONE")
