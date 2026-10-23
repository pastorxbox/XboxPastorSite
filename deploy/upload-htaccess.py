#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/.htaccess", f"{REMOTE}/.htaccess"),
    ("public/uploads/.htaccess", f"{REMOTE}/uploads/.htaccess"),
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
    print(f"put {path.name} -> {remote}")
    sftp.put(str(path), remote)
sftp.close()
_, o, _ = c.exec_command(
    "curl -sI -A Mozilla "
    "'https://pastorxbox.com.br/uploads/2026/06/amazon-luna-free-pc-games-banner-june-2026-1200x600.avif' "
    "| tr -d '\\r' | grep -iE 'HTTP/|content-type'"
)
print(o.read().decode(errors="replace"))
c.close()
