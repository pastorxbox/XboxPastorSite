#!/usr/bin/env python3
"""Diagnose store_url on Hostinger DB + live HTML."""
import os
import json

import paramiko

PASSWORD = os.environ["SSHPASS"]
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

cmds = [
    r"""set -a; . domains/pastorxbox.com.br/config/.env; set +a
DBN="${DB_NAME:-$DB_DATABASE}"; DBU="${DB_USER:-$DB_USERNAME}"; DBP="${DB_PASS:-$DB_PASSWORD}"
export MYSQL_PWD="$DBP"
mysql -h localhost -u "$DBU" "$DBN" -e "
SELECT COUNT(*) AS total,
       SUM(store_url IS NOT NULL AND store_url<>'') AS with_url
FROM gp_releases;
SELECT id, title, LEFT(IFNULL(store_url,'(null)'),90) AS store_url
FROM gp_releases
ORDER BY updated_at DESC
LIMIT 20;"
""",
    r"""curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' | grep -c 'cal-card__store' || true""",
    r"""curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' | grep -oE 'cal-card__store|🛍️ na loja|Chega em [^<]+' | head -40""",
    r"""grep -n 'store_url\|🛍️' domains/pastorxbox.com.br/public_html/includes/calendar.php | head -25""",
]
for i, cmd in enumerate(cmds, 1):
    print(f"=== {i} ===")
    _, o, e = c.exec_command(cmd, timeout=60)
    print(o.read().decode(errors="replace"))
    err = e.read().decode(errors="replace")
    if err.strip():
        print("ERR:", err[:500])
c.close()
