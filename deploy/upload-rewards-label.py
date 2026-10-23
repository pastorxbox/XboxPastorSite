#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
files = [
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/tvxbox.php", f"{REMOTE}/includes/tvxbox.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
]
c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "147.93.39.20",
    port=65002,
    username="u285071835",
    password=os.environ["SSHPASS"],
    allow_agent=False,
    look_for_keys=False,
    timeout=45,
)
sftp = c.open_sftp()
for local, remote in files:
    print("put", local)
    sftp.put(str(ROOT / local), remote)
sftp.close()
_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/?lang=pt' | grep -c 20260918tvmodal7; "
    "grep -n 'data-rewards-points-tpl\\|rewards_points' "
    "domains/pastorxbox.com.br/public_html/includes/tvxbox.php "
    "domains/pastorxbox.com.br/public_html/assets/js/vitrine.js "
    "domains/pastorxbox.com.br/public_html/includes/calendar.php | head -12",
    timeout=60,
)
print(o.read().decode())
c.close()
print("DONE")
