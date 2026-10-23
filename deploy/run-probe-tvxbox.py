#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
PASSWORD = os.environ["SSHPASS"]
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
sftp.put(str(ROOT / "deploy/_probe_tvxbox.php"), "domains/pastorxbox.com.br/public_html/_probe_tvxbox.php")
sftp.close()
_, o, e = c.exec_command(
    "cd domains/pastorxbox.com.br/public_html && php _probe_tvxbox.php; rm -f _probe_tvxbox.php",
    timeout=60,
)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace")[:1500])
c.close()
