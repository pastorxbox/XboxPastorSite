#!/usr/bin/env python3
"""Deploy home carousel calendar events."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/index.php", f"{REMOTE}/index.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
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
for local, remote in files:
    print("put", local)
    sftp.put(str(ROOT / local), remote)
sftp.close()

# Ensure there is a published event starting today for smoke (restore-safe update of a test title if needed)
probe = r"""
set -a; . domains/pastorxbox.com.br/config/.env; set +a
mysql -h "$DB_HOST" -u "$DB_USER" -p"$DB_PASSWORD" "$DB_NAME" -N -e "
SELECT id, title, DATE(starts_at) d FROM calendar_events WHERE status='published' AND DATE(starts_at)=CURDATE();
SELECT id, title, DATE(starts_at) d FROM calendar_events WHERE status='published' AND DATE(starts_at)=DATE_SUB(CURDATE(), INTERVAL 1 DAY);
SELECT CURDATE();
"
"""
_, o, _ = c.exec_command(probe, timeout=60)
print("DB events today/yesterday:")
print(o.read().decode(errors="replace"))

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/?lang=pt' "
    "| grep -oE 'cat--events|Hoje é Dia de|home-events-below|card--event|calendario#eventos|20260916evcar1' "
    "| sort | uniq -c",
    timeout=60,
)
print("HOME:")
print(o.read().decode(errors="replace"))
c.close()
print("DONE")
