#!/usr/bin/env python3
"""Upload mobile calendar CSS fixes."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
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
    timeout=60,
)
sftp = c.open_sftp()
for local, remote in files:
    print(f"put {Path(local).name}")
    sftp.put(str(ROOT / local), remote)
sftp.close()

_, o, e = c.exec_command(
    "grep -n 'gp-filters {\\|align-self: stretch\\|20260908mobilecal' "
    f"{REMOTE}/assets/css/vitrine.css {REMOTE}/includes/vitrine-layout.php | head -20",
    timeout=30,
)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace")[:300])
c.close()
print("DONE")
