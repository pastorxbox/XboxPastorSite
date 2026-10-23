#!/usr/bin/env python3
"""Deploy store link UI/API fix."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/includes/api-calendar.php", f"{REMOTE}/includes/api-calendar.php"),
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
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
    timeout=60,
    banner_timeout=60,
)
sftp = c.open_sftp()
for local, remote in files:
    path = ROOT / local
    print(f"put {path.name}")
    sftp.put(str(path), remote)
sftp.close()

_, o, e = c.exec_command(
    r"""set -a; . domains/pastorxbox.com.br/config/.env; set +a
DBN="${DB_NAME:-$DB_DATABASE}"; DBU="${DB_USER:-$DB_USERNAME}"; DBP="${DB_PASS:-$DB_PASSWORD}"
export MYSQL_PWD="$DBP"
mysql -h localhost -u "$DBU" "$DBN" -N -e "SELECT COUNT(*) total, SUM(store_url IS NOT NULL AND store_url<>'') with_url FROM gp_releases;"
echo ---
grep -n "🛍️ na loja\|storeUrl\|store_url" domains/pastorxbox.com.br/public_html/includes/calendar.php domains/pastorxbox.com.br/public_html/includes/api-calendar.php | head -20
""",
    timeout=45,
)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR:", err[:400])
c.close()
print("DONE")
