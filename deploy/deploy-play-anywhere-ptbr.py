#!/usr/bin/env python3
"""Migrate play_anywhere/supports_pt_br and upload calendar badge UI to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/assets/img/play-anywhere.png", f"{REMOTE}/assets/img/play-anywhere.png"),
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/api-calendar.php", f"{REMOTE}/includes/api-calendar.php"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("sql/011_releases_play_anywhere_ptbr.sql", "domains/pastorxbox.com.br/sql/011_releases_play_anywhere_ptbr.sql"),
    ("sql/004_calendar.sql", "domains/pastorxbox.com.br/sql/004_calendar.sql"),
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
ENVF=domains/pastorxbox.com.br/config/.env
if [ ! -f "$ENVF" ]; then ENVF=domains/pastorxbox.com.br/public_html/../config/.env; fi
export $(grep -E '^(DB_HOST|DB_NAME|DB_USER|DB_PASS|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=' domains/pastorxbox.com.br/config/.env 2>/dev/null | xargs) || true
DBN="${DB_NAME:-$DB_DATABASE}"
DBU="${DB_USER:-$DB_USERNAME}"
DBP="${DB_PASS:-$DB_PASSWORD}"
DBH="${DB_HOST:-localhost}"
echo "Migrating $DBN @ $DBH as $DBU"
for COL in play_anywhere supports_pt_br; do
  HAS=$(mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -N -e "SHOW COLUMNS FROM gp_releases LIKE '$COL';" | wc -l)
  echo "col $COL present=$HAS"
done
HAS_PA=$(mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -N -e "SHOW COLUMNS FROM gp_releases LIKE 'play_anywhere';" | wc -l)
if [ "$HAS_PA" = "0" ]; then
  mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" < domains/pastorxbox.com.br/sql/011_releases_play_anywhere_ptbr.sql
  echo "Columns added"
else
  echo "Columns already present"
fi
mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -e "SHOW COLUMNS FROM gp_releases LIKE 'play_anywhere'; SHOW COLUMNS FROM gp_releases LIKE 'supports_pt_br';"
"""
_, o, e = c.exec_command(migrate, timeout=90)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:800])

_, o, e = c.exec_command(
    "curl -sL -A Mozilla -o /dev/null -w 'cal:%{http_code}\\n' 'https://pastorxbox.com.br/calendario'; "
    "curl -sL -A Mozilla -o /dev/null -w 'img:%{http_code}\\n' 'https://pastorxbox.com.br/assets/img/play-anywhere.png'; "
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' | grep -oE 'cal-card__corner-badges|cal-card__xpa|play-anywhere.png|cal-card__ptbr' | sort | uniq -c",
    timeout=60,
)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR:", err[:400])
c.close()
print("DONE")
