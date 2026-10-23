#!/usr/bin/env python3
"""Upload home chrome i18n (PT/EN/ES + date formats) to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/helpers.php", f"{REMOTE}/includes/helpers.php"),
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/index.php", f"{REMOTE}/index.php"),
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
        "EN brand+home",
        "curl -sL -A Mozilla -b 'px_lang=en' 'https://pastorxbox.com.br/?lang=en' "
        "| grep -oE 'Xbox, Games &amp; Purpose|Latest news|min read|All rights reserved|Latest|Follow the latest' "
        "| sort | uniq -c",
    ),
    (
        "ES brand+home",
        "curl -sL -A Mozilla -b 'px_lang=es' 'https://pastorxbox.com.br/?lang=es' "
        "| grep -oE 'Xbox, Games &amp; Propósito|Últimas noticias|min de lectura|Más recientes|Aceptar' "
        "| sort | uniq -c",
    ),
    (
        "PT brand",
        "curl -sL -A Mozilla -b 'px_lang=pt' 'https://pastorxbox.com.br/?lang=pt' "
        "| grep -oE 'Xbox, Games &amp; Propósito|Últimas notícias|min de leitura' "
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
