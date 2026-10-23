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
sftp.put(str(ROOT / "deploy/_probe_tvxbox_meta.php"), f"{REMOTE}/_probe_tvxbox_meta.php")
sftp.close()
_, o, e = c.exec_command(
    f"cd {REMOTE} && php _probe_tvxbox_meta.php; rm -f _probe_tvxbox_meta.php",
    timeout=90,
)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR", err[:2000])
c.close()
