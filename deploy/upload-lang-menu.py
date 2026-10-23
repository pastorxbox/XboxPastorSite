#!/usr/bin/env python3
"""Upload language switcher popup UI to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
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
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/noticias?lang=es' "
    "| grep -oE 'lang-switcher|lang-switcher__toggle|lang-switcher__menu|🇧🇷|🇬🇧|🇪🇸|Português|English|Español' "
    "| sort | uniq -c",
    timeout=60,
)
print("SMOKE:")
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR:", err[:500])
c.close()
print("DONE")
