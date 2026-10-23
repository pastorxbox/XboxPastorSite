#!/usr/bin/env python3
"""Migrate expires_at + upload TV Xbox comment/GP pill + auto-expire."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("sql/017_tv_videos_expires_at.sql", "domains/pastorxbox.com.br/sql/017_tv_videos_expires_at.sql"),
    ("public/includes/tvxbox.php", f"{REMOTE}/includes/tvxbox.php"),
    ("public/includes/api-tvxbox.php", f"{REMOTE}/includes/api-tvxbox.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
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
COL=$(mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -N -e "SHOW COLUMNS FROM tv_videos LIKE 'expires_at';" | wc -l)
if [ "$COL" = "0" ]; then
  mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" < domains/pastorxbox.com.br/sql/017_tv_videos_expires_at.sql
  echo "Column expires_at added"
else
  echo "Column expires_at already present"
fi
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "SHOW COLUMNS FROM tv_videos LIKE 'expires_at';"
"""
_, o, e = c.exec_command(migrate, timeout=60)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:800])

checks = [
    (
        "comment UI",
        "curl -sL -A Mozilla 'https://pastorxbox.com.br/?lang=pt' "
        "| grep -oE 'tvxbox__comment--gp|tvxbox__gp-slot|site-ticker__gp-badge|20260915tvexp1|onGamePass' "
        "| sort | uniq -c",
    ),
]
for label, cmd in checks:
    print(f"\nSMOKE {label}:")
    _, o, e = c.exec_command(cmd, timeout=60)
    print(o.read().decode(errors="replace"))

c.close()
print("DONE")
