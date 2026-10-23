#!/usr/bin/env python3
"""Upload site search files to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
HOST = "147.93.39.20"
PORT = 65002
USER = "u285071835"
PASSWORD = os.environ["SSHPASS"]
REMOTE_BASE = "domains/pastorxbox.com.br/public_html"

files = [
    ("public/assets/css/vitrine.css", f"{REMOTE_BASE}/assets/css/vitrine.css"),
    ("public/assets/js/vitrine.js", f"{REMOTE_BASE}/assets/js/vitrine.js"),
    ("public/includes/vitrine-layout.php", f"{REMOTE_BASE}/includes/vitrine-layout.php"),
    ("public/includes/helpers.php", f"{REMOTE_BASE}/includes/helpers.php"),
    ("public/includes/posts.php", f"{REMOTE_BASE}/includes/posts.php"),
    ("public/includes/tags.php", f"{REMOTE_BASE}/includes/tags.php"),
    ("public/includes/calendar.php", f"{REMOTE_BASE}/includes/calendar.php"),
    ("public/includes/content.php", f"{REMOTE_BASE}/includes/content.php"),
    ("public/index.php", f"{REMOTE_BASE}/index.php"),
    ("public/.htaccess", f"{REMOTE_BASE}/.htaccess"),
]

client = paramiko.SSHClient()
client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
client.connect(
    HOST,
    port=PORT,
    username=USER,
    password=PASSWORD,
    allow_agent=False,
    look_for_keys=False,
    timeout=45,
)
sftp = client.open_sftp()
for local, remote in files:
    path = ROOT / local
    print(f"put {path.name} -> {remote}")
    sftp.put(str(path), remote)
sftp.close()
_, o, e = client.exec_command(
    f"grep -n 'render_pagination_nav\\|pagination_items\\|assetVersion' "
    f"{REMOTE_BASE}/includes/helpers.php {REMOTE_BASE}/includes/vitrine-layout.php | head -15; "
    f"curl -sS -A Mozilla -o /dev/null -w 'home:%{{http_code}}\\n' 'https://pastorxbox.com.br/'; "
    f"curl -sS -A Mozilla 'https://pastorxbox.com.br/' | grep -oE 'pagination[^\"<> ]*|pagination__ellipsis|aria-label=\"Paginação\"' | head -15; echo"
)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print(err)
client.close()
print("DONE")
