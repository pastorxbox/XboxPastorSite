#!/usr/bin/env python3
"""Upload YouTube parse fix and verify TCG appears on TV Xbox."""
import json
import os
import re
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
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
sftp.put(str(ROOT / "public/includes/tvxbox.php"), f"{REMOTE}/includes/tvxbox.php")
sftp.put(str(ROOT / "deploy/_probe_tvxbox.php"), f"{REMOTE}/_probe_tvxbox.php")
sftp.close()

_, o, e = c.exec_command(
    f"cd {REMOTE} && php _probe_tvxbox.php; rm -f _probe_tvxbox.php",
    timeout=60,
)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:800])

_, o, _ = c.exec_command(
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' | tr '\\n' ' ' "
    "| grep -oE 'data-videos=\"[^\"]+\"' | head -1",
    timeout=60,
)
raw = o.read().decode(errors="replace")
m = re.search(r'data-videos="([^"]*)"', raw)
if m:
    html = (
        m.group(1)
        .replace("&quot;", '"')
        .replace("&amp;", "&")
    )
    videos = json.loads(html)
    ids = [v.get("youtubeId") for v in videos]
    print(f"homepage videos={len(videos)} hasTCG={'3OG4XeEcYjg' in ids} hasHela={'Iz9dEXYRgO8' in ids}")
    for v in videos:
        if v.get("youtubeId") in ("3OG4XeEcYjg", "Iz9dEXYRgO8"):
            print(" ", v)

c.close()
print("DONE")
