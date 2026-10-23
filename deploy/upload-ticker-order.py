#!/usr/bin/env python3
"""Deploy ticker nearest-first ordering."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]
files = [
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
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
    timeout=60,
)
sftp = c.open_sftp()
for local, remote in files:
    print("put", Path(local).name)
    sftp.put(str(ROOT / local), remote)
sftp.close()
_, o, e = c.exec_command(
    f"grep -n 'sort_at\\|20260908tickerord\\|Rola para a esquerda' "
    f"{REMOTE}/includes/calendar.php {REMOTE}/includes/vitrine-layout.php {REMOTE}/assets/js/vitrine.js | head -20",
    timeout=30,
)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace")[:300])
c.close()
print("DONE")
