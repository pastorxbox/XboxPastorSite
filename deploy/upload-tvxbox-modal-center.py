#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
files = [
    ("public/includes/tvxbox.php", f"{REMOTE}/includes/tvxbox.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
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
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/?lang=pt' | grep -oE '20260918tvmodal10' | head -2; "
    "grep -nE 'align-items: center|#107c10|tvxbox-modal__store' "
    "domains/pastorxbox.com.br/public_html/assets/css/vitrine.css | head -15"
)
print(o.read().decode())
c.close()
print("DONE")
