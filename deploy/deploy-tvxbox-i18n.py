#!/usr/bin/env python3
"""Deploy TV Xbox EN/ES translations API to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("sql/019_tv_video_translations.sql", "domains/pastorxbox.com.br/sql/019_tv_video_translations.sql"),
    ("public/.htaccess", f"{REMOTE}/.htaccess"),
    ("public/api/index.php", f"{REMOTE}/api/index.php"),
    ("public/includes/api-tvxbox.php", f"{REMOTE}/includes/api-tvxbox.php"),
    ("public/includes/tvxbox.php", f"{REMOTE}/includes/tvxbox.php"),
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
set -a
. domains/pastorxbox.com.br/config/.env
set +a
DBN="${DB_NAME:-$DB_DATABASE}"
DBU="${DB_USER:-$DB_USERNAME}"
DBP="${DB_PASSWORD:-$DB_PASS}"
DBH="${DB_HOST:-localhost}"
echo "Migrating $DBN @ $DBH as $DBU"
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" < domains/pastorxbox.com.br/sql/019_tv_video_translations.sql
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "SHOW TABLES LIKE 'tv_video_translations';"
"""
_, o, e = c.exec_command(migrate, timeout=120)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:1200])

c.close()
print("DONE")
