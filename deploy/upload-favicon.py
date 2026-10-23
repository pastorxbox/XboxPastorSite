#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/favicon.ico", f"{REMOTE}/favicon.ico"),
    ("public/assets/img/favicon.ico", f"{REMOTE}/assets/img/favicon.ico"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
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
    path = ROOT / local
    print(f"put {path} -> {remote} ({path.stat().st_size} bytes)")
    sftp.put(str(path), remote)
sftp.close()
_, o, _ = c.exec_command(
    "curl -sI -A Mozilla 'https://pastorxbox.com.br/favicon.ico?v=20260904favicon' "
    "| tr -d '\\r' | grep -iE 'HTTP/|content-type|content-length'; "
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' | grep -i favicon"
)
print(o.read().decode(errors="replace"))
c.close()
print("DONE")
