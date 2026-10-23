#!/usr/bin/env python3
"""Upload ticker today highlight + TV Xbox i18n."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/tvxbox.php", f"{REMOTE}/includes/tvxbox.php"),
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

checks = [
    (
        "PT ticker today",
        "curl -sL -A Mozilla -b 'px_lang=pt' 'https://pastorxbox.com.br/?lang=pt' "
        "| grep -oE 'site-ticker__item--today|Disponível HOJE no Game Pass|Disponível HOJE no Xbox|20260915tick1' "
        "| sort | uniq -c",
    ),
    (
        "EN TV Xbox",
        "curl -sL -A Mozilla -b 'px_lang=en' 'https://pastorxbox.com.br/?lang=en' "
        "| grep -oE 'Xbox TV|Game Pass launch on|Xbox launch on|Available TODAY on Game Pass|Available TODAY on Xbox' "
        "| sort | uniq -c",
    ),
    (
        "ES TV Xbox",
        "curl -sL -A Mozilla -b 'px_lang=es' 'https://pastorxbox.com.br/?lang=es' "
        "| grep -oE 'TV Xbox|Lanzamiento en Game Pass|Lanzamiento en Xbox|Disponible HOY en Game Pass|Disponible HOY en Xbox' "
        "| sort | uniq -c",
    ),
]

for label, cmd in checks:
    print(f"\nSMOKE {label}:")
    _, o, e = c.exec_command(cmd, timeout=60)
    print(o.read().decode(errors="replace"))
    err = e.read().decode(errors="replace")
    if err.strip():
        print("ERR:", err[:400])

c.close()
print("DONE")
