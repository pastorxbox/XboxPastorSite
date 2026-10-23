#!/usr/bin/env python3
"""Upload brand-logo sync files to Hostinger. Requires SSHPASS."""
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ.get("SSHPASS", "")
if not PASSWORD:
    raise SystemExit("SSHPASS required")

files = [
    ("public/.htaccess", f"{REMOTE}/.htaccess"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/api-brand.php", f"{REMOTE}/includes/api-brand.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/api/index.php", f"{REMOTE}/api/index.php"),
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
    print(f"put {path} -> {remote} ({path.stat().st_size} bytes)")
    sftp.put(str(path), remote)
sftp.close()
_, o, e = c.exec_command(
    "curl -sS -o /dev/null -w '%{http_code}' -H 'Authorization: Bearer ' "
    "-X GET 'https://pastorxbox.com.br/api/settings/brand' || true; echo; "
    "ls -la domains/pastorxbox.com.br/public_html/includes/api-brand.php"
)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
