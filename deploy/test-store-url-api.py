#!/usr/bin/env python3
"""Test POST store_url to site API for Cold War (id=5) and verify DB + HTML."""
import json
import os
import urllib.request

import paramiko

PASSWORD = os.environ["SSHPASS"]
API_KEY = Path_API = None

# read key from repo file
from pathlib import Path

API_KEY = Path("/mnt/c/Projetos/XboxPastorSite/deploy/SITE_API_KEY.txt").read_text().strip()
STORE = "https://www.xbox.com/pt-BR/games/store/call-of-duty-black-ops-cold-war/9N4ZQH1XQ3K4"

# Fetch current item via Hostinger PHP using mysql + curl API
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

# Get current row as JSON-ish via mysql
cmd = r"""
set -a; . domains/pastorxbox.com.br/config/.env; set +a
DBN="${DB_NAME:-$DB_DATABASE}"; DBU="${DB_USER:-$DB_USERNAME}"; DBP="${DB_PASS:-$DB_PASSWORD}"
export MYSQL_PWD="$DBP"
mysql -h localhost -u "$DBU" "$DBN" -N -e "SELECT id,title,cover_url,release_at,status,on_gamepass,on_ultimate,on_premium,on_essential,on_pc,platform_console,platform_pc,platform_cloud,IFNULL(body,''),IFNULL(slug,''),sort_order FROM gp_releases WHERE id=5\G"
"""
_, o, e = c.exec_command(cmd, timeout=30)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace")[:300])

# Use PHP one-liner on server to call update through same code path? Prefer curl to public API
update = f"""
curl -sS -X POST 'https://pastorxbox.com.br/api/calendar/gamepass/5' \\
  -H 'Authorization: Bearer {API_KEY}' \\
  -H 'Content-Type: application/json' \\
  -d @- <<'JSON'
{{
  "title": "Call of Duty®: Black Ops Cold War",
  "cover_url": null,
  "store_url": "{STORE}",
  "release_at": "2026-09-08 17:06:00",
  "body": null,
  "on_gamepass": true,
  "on_ultimate": true,
  "on_premium": true,
  "on_essential": false,
  "on_pc": true,
  "platform_console": true,
  "platform_pc": true,
  "platform_cloud": true,
  "status": "published",
  "sort_order": 0
}}
JSON
"""
# Don't wipe cover - fetch cover first
fetch = f"""
curl -sS 'https://pastorxbox.com.br/api/calendar/gamepass/5' -H 'Authorization: Bearer {API_KEY}'
"""
_, o, e = c.exec_command(fetch, timeout=45)
raw = o.read().decode(errors="replace")
print("GET item:", raw[:800])
err = e.read().decode(errors="replace")
if err.strip():
    print("GET ERR", err[:400])

try:
    item = json.loads(raw)["item"]
except Exception as ex:
    print("parse fail", ex)
    c.close()
    raise SystemExit(1)

payload = {
    "title": item["title"],
    "slug": item.get("slug"),
    "cover_url": item.get("cover_url"),
    "store_url": STORE,
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
body = json.dumps(payload, ensure_ascii=False)
# write temp and curl
sftp = c.open_sftp()
with sftp.file("/tmp/gp5-update.json", "w") as f:
    f.write(body)
sftp.close()

post = f"""
curl -sS -w '\\nHTTP:%{{http_code}}\\n' -X POST 'https://pastorxbox.com.br/api/calendar/gamepass/5' \
  -H 'Authorization: Bearer {API_KEY}' \
  -H 'Content-Type: application/json' \
  --data-binary @/tmp/gp5-update.json
"""
_, o, e = c.exec_command(post, timeout=45)
print("POST:", o.read().decode(errors="replace"))
print(e.read().decode(errors="replace")[:400])

verify = r"""
set -a; . domains/pastorxbox.com.br/config/.env; set +a
DBN="${DB_NAME:-$DB_DATABASE}"; DBU="${DB_USER:-$DB_USERNAME}"; DBP="${DB_PASS:-$DB_PASSWORD}"
export MYSQL_PWD="$DBP"
mysql -h localhost -u "$DBU" "$DBN" -e "SELECT id, LEFT(store_url,100) store_url FROM gp_releases WHERE id=5;"
curl -sL -A Mozilla 'https://pastorxbox.com.br/calendario' | grep -oE 'cal-card__store[^>]*>[^<]+|🛍️ na loja' | head -10
"""
_, o, e = c.exec_command(verify, timeout=45)
print(o.read().decode(errors="replace"))
c.close()
