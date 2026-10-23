#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
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
sftp.put(
    str(ROOT / "public/includes/calendar.php"),
    "domains/pastorxbox.com.br/public_html/includes/calendar.php",
)
sftp.close()
print("uploaded")
_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' "
    "| grep -o 'Faltam [0-9]* dias para [^<]*' | head -3"
)
print(o.read().decode(errors="replace"))
c.close()
