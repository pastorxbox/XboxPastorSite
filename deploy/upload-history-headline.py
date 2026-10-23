#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]
files = [
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
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
    print("put", local)
    sftp.put(str(ROOT / local), remote)
sftp.close()

checks = [
    (
        "PT",
        "curl -sL -A Mozilla -b 'px_lang=pt' 'https://pastorxbox.com.br/calendario?lang=pt' "
        "| grep -oE 'aconteceu [0-9]+ anos atrás|aconteceu há [0-9]+' | head -5",
    ),
    (
        "EN",
        "curl -sL -A Mozilla -b 'px_lang=en' 'https://pastorxbox.com.br/calendario?lang=en' "
        "| grep -oE 'happened [0-9]+ years ago[^<]{0,40}' | head -5",
    ),
    (
        "ES",
        "curl -sL -A Mozilla -b 'px_lang=es' 'https://pastorxbox.com.br/calendario?lang=es' "
        "| grep -oE 'ocurrió hace [0-9]+ años[^<]{0,40}' | head -5",
    ),
]
for label, cmd in checks:
    print(f"\nSMOKE {label}:")
    _, o, _ = c.exec_command(cmd, timeout=60)
    print(o.read().decode(errors="replace"))

c.close()
print("DONE")
