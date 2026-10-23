#!/usr/bin/env python3
"""Upload ad dimension CSS fix + asset version bump to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ.get("SSHPASS") or os.environ.get("HOSTINGER_SSH_PASS")
if not PASSWORD:
    raise SystemExit("Set SSHPASS (or HOSTINGER_SSH_PASS) before running.")

files = [
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/includes/ads.php", f"{REMOTE}/includes/ads.php"),
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

_, o, e = c.exec_command(
    "grep -n 'px-banner--max-h\\|20260916ads230' "
    "domains/pastorxbox.com.br/public_html/assets/css/vitrine.css "
    "domains/pastorxbox.com.br/public_html/includes/vitrine-layout.php | head -20; "
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' | grep -oE 'vitrine.css\\?v=[^\" ]+|px-h:[0-9]+px' | head -10",
    timeout=60,
)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:500])
c.close()
print("done")
