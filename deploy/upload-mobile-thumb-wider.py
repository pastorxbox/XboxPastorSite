#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
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
sftp.put(str(ROOT / "public/assets/css/vitrine.css"), f"{REMOTE}/assets/css/vitrine.css")
sftp.put(str(ROOT / "public/includes/vitrine-layout.php"), f"{REMOTE}/includes/vitrine-layout.php")
sftp.close()
_, o, _ = c.exec_command(
    "grep -n '148px\\|object-fit: contain' domains/pastorxbox.com.br/public_html/assets/css/vitrine.css | head -10; "
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/?lang=pt' | grep -c 20260916mlist2",
    timeout=60,
)
print(o.read().decode())
c.close()
print("DONE")
