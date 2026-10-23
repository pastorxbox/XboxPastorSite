#!/usr/bin/env python3
"""Check store_url data and card markup on Hostinger."""
import os

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
    timeout=45,
)

cmds = [
    r"""set -a; . domains/pastorxbox.com.br/config/.env; set +a
DBN="${DB_NAME:-$DB_DATABASE}"; DBU="${DB_USER:-$DB_USERNAME}"; DBP="${DB_PASS:-$DB_PASSWORD}"
export MYSQL_PWD="$DBP"
mysql -h localhost -u "$DBU" "$DBN" -e "SHOW COLUMNS FROM gp_releases LIKE 'store_url'; SELECT id,title,IFNULL(store_url,'(null)') AS store_url FROM gp_releases ORDER BY release_at DESC LIMIT 20; SELECT COUNT(*) AS total, SUM(store_url IS NOT NULL AND store_url<>'') AS with_url FROM gp_releases;"
""",
    r"""grep -n 'store_url\|external_url\|storeUrl\|xbox' domains/pastorxbox.com.br/public_html/includes/api-calendar.php | head -40""",
    r"""curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' | grep -c 'cal-card__store' || true""",
    r"""curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' | grep -oE 'cal-card__store|Chega em [^<]+' | head -30""",
]
for i, cmd in enumerate(cmds, 1):
    print(f"=== CMD {i} ===")
    _, o, e = c.exec_command(cmd, timeout=60)
    print(o.read().decode(errors="replace"))
    err = e.read().decode(errors="replace")
    if err.strip():
        print("ERR:", err[:600])

c.close()
