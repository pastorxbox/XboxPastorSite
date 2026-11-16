#!/usr/bin/env python3
"""Smoke das rotas Fase 2 após upload do .htaccess."""
import os
import sys

import paramiko

PASSWORD = os.environ.get("SSHPASS")
if not PASSWORD:
    print("SSHPASS required", file=sys.stderr)
    sys.exit(1)

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

# Upload htaccess only if present locally via second script; this is smoke-only.
sftp = c.open_sftp()
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
sftp.put(str(ROOT / "public/.htaccess"), f"{REMOTE}/.htaccess")
print("put .htaccess")
sftp.close()

smoke = r"""
set -e
grep -nE 'catalog|catalog-link' domains/pastorxbox.com.br/public_html/.htaccess | head -10
echo '---'
curl -sL -o /tmp/catalog_smoke.json -w 'catalog:%{http_code}\n' -X PUT \
  -H 'Content-Type: application/json' \
  -d '{"manager_game_id":"smoke","slug":"smoke","title":"Smoke","do_not_overwrite_price_brl":true,"products":[]}' \
  'https://pastorxbox.com.br/api/catalog/games/smoke'
head -c 300 /tmp/catalog_smoke.json; echo
echo '---'
curl -sL -o /tmp/catalog_link_smoke.json -w 'link:%{http_code}\n' -X PUT \
  -H 'Content-Type: application/json' \
  -d '{"catalog_game_id":null}' \
  'https://pastorxbox.com.br/api/calendar/gamepass/1/catalog-link'
head -c 300 /tmp/catalog_link_smoke.json; echo
echo '---'
curl -sL -o /tmp/posts_smoke.json -w 'posts:%{http_code}\n' -X GET \
  'https://pastorxbox.com.br/api/posts'
head -c 200 /tmp/posts_smoke.json; echo
"""
_, o, e = c.exec_command(smoke, timeout=60)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR:", err[:500])
c.close()
print("DONE")
