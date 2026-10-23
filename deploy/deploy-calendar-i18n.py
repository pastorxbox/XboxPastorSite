#!/usr/bin/env python3
"""Deploy calendar EN/ES translations API to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/.htaccess", f"{REMOTE}/.htaccess"),
    ("public/api/index.php", f"{REMOTE}/api/index.php"),
    ("public/includes/api-calendar.php", f"{REMOTE}/includes/api-calendar.php"),
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("sql/011_calendar_i18n.sql", "domains/pastorxbox.com.br/sql/011_calendar_i18n.sql"),
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
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" < domains/pastorxbox.com.br/sql/011_calendar_i18n.sql
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "SHOW TABLES LIKE 'calendar_translations';"
"""
_, o, e = c.exec_command(migrate, timeout=120)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:1200])

c.close()
print("DONE")
