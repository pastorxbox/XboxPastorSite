#!/usr/bin/env python3
"""Upload api-calendar.php (catalog_game_id no GET) para Hostinger."""
import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ.get("SSHPASS")
if not PASSWORD:
    print("SSHPASS required", file=sys.stderr)
    sys.exit(1)

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
local = ROOT / "public/includes/api-calendar.php"
remote = f"{REMOTE}/includes/api-calendar.php"
print(f"put {local.name} -> {remote}")
sftp.put(str(local), remote)
sftp.close()

_, o, e = c.exec_command(
    "grep -n 'api_gamepass_with_catalog_link\\|catalog_game_id' "
    "domains/pastorxbox.com.br/public_html/includes/api-calendar.php | head -20",
    timeout=30,
)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR:", err[:300])
c.close()
print("DONE")
