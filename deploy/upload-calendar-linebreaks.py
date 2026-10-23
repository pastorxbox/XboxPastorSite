#!/usr/bin/env python3
"""Deploy calendar line-break fix to Hostinger. Requires SSHPASS."""
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ.get("SSHPASS", "")
if not PASSWORD:
    raise SystemExit("SSHPASS required")

files = [
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/api-calendar.php", f"{REMOTE}/includes/api-calendar.php"),
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
    timeout=45,
)
sftp = c.open_sftp()
for local, remote in files:
    path = ROOT / local
    print(f"put {path.name} ({path.stat().st_size} bytes)")
    sftp.put(str(path), remote)
sftp.close()
_, o, e = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' "
    "| tr -d '\\r' | grep -o 'sidebar-event__excerpt[^<]*' | head -3; "
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' "
    "| tr -d '\\r' | grep -c '<br' || true"
)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
