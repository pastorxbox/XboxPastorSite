#!/usr/bin/env python3
"""Clear fake test store_url on id=8."""
import json
import os
from pathlib import Path

import paramiko

API_KEY = Path("/mnt/c/Projetos/XboxPastorSite/deploy/SITE_API_KEY.txt").read_text().strip()
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
    timeout=60,
)
_, o, _ = c.exec_command(
    f"curl -sS 'https://pastorxbox.com.br/api/calendar/gamepass/8' -H 'Authorization: Bearer {API_KEY}'",
    timeout=45,
)
item = json.loads(o.read().decode())["item"]
payload = {
    "title": item["title"],
    "slug": item.get("slug"),
    "cover_url": item.get("cover_url"),
    "store_url": None,
    "release_at": item["release_at"],
    "body": item.get("body"),
    "on_gamepass": bool(item.get("on_gamepass")),
    "on_ultimate": bool(item.get("on_ultimate")),
    "on_premium": bool(item.get("on_premium")),
    "on_essential": bool(item.get("on_essential")),
    "on_pc": bool(item.get("on_pc")),
    "platform_console": bool(item.get("platform_console")),
    "platform_pc": bool(item.get("platform_pc")),
    "platform_cloud": bool(item.get("platform_cloud")),
    "status": item.get("status") or "published",
    "sort_order": int(item.get("sort_order") or 0),
}
sftp = c.open_sftp()
with sftp.file("/tmp/gp8-clear.json", "w") as f:
    f.write(json.dumps(payload))
sftp.close()
_, o, e = c.exec_command(
    f"curl -sS -X POST 'https://pastorxbox.com.br/api/calendar/gamepass/8' "
    f"-H 'Authorization: Bearer {API_KEY}' -H 'Content-Type: application/json' "
    f"--data-binary @/tmp/gp8-clear.json",
    timeout=45,
)
print(o.read().decode())
print(e.read().decode()[:200])
c.close()
print("cleared id 8")
