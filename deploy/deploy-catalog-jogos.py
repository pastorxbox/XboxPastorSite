#!/usr/bin/env python3
"""Fase 5 — Vitrine pública /jogos (sem ícone na home/nav)."""
import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ.get("SSHPASS")
if not PASSWORD:
    print("SSHPASS env var required", file=sys.stderr)
    sys.exit(1)

files = [
    ("public/.htaccess", f"{REMOTE}/.htaccess"),
    ("public/index.php", f"{REMOTE}/index.php"),
    ("public/includes/catalog.php", f"{REMOTE}/includes/catalog.php"),
    ("public/includes/catalog-vitrine.php", f"{REMOTE}/includes/catalog-vitrine.php"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
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
    timeout=45,
)
sftp = c.open_sftp()
for local, remote in files:
    path = ROOT / local
    if not path.is_file():
        print(f"MISSING {path}")
        sftp.close()
        c.close()
        sys.exit(1)
    print(f"put {path.name} -> {remote}")
    sftp.put(str(path), remote)
sftp.close()
c.close()
print("OK deploy /jogos")
