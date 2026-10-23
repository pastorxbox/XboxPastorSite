#!/usr/bin/env python3
"""Diagnose GA4 wiring on Hostinger."""
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
    "grep -E '^GA_MEASUREMENT_ID=' domains/pastorxbox.com.br/config/.env",
    "php -r '$c=require \"domains/pastorxbox.com.br/config/config.php\"; echo ($c[\"ga_measurement_id\"]??\"\").PHP_EOL;'",
    "curl -sL -A Mozilla https://pastorxbox.com.br/ | grep -oE 'data-ga-id=\"[^\"]*\"|id=\"cookie-accept\"|cookie-notice|vitrine\\.js\\?v=[^\"]+' | head -20",
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/assets/js/vitrine.js?v=20260904rsscal' | sed -n '1,60p'",
    "grep -n 'is-hidden\\|cookie-notice' domains/pastorxbox.com.br/public_html/assets/css/vitrine.css | head -20",
]
for cmd in cmds:
    print(">>>", cmd[:100])
    _, o, e = c.exec_command(cmd, timeout=45)
    print(o.read().decode(errors="replace"))
    err = e.read().decode(errors="replace")
    if err.strip():
        print("ERR:", err[:300])
c.close()
