#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
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
    print(f"put {local}")
    sftp.put(str(ROOT / local), remote)
sftp.close()

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' "
    "| grep -oE '20260915tvrand1|takeNextIndex|shuffleIndices' | sort | uniq -c"
)
print(o.read().decode(errors="replace"))
c.close()
print("DONE")
