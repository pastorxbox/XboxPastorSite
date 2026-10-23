#!/usr/bin/env python3
"""From Oracle Manager, POST store_url like the app would; inspect SITE_ env."""
from pathlib import Path

import paramiko

KEY = Path("/mnt/c/Projetos/oracle/ssh-key-2026-06-11.key")


def connect():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        pkey = paramiko.Ed25519Key.from_private_key_file(str(KEY))
    except Exception:
        pkey = paramiko.RSAKey.from_private_key_file(str(KEY))
    c.connect("147.15.79.91", username="ubuntu", pkey=pkey, timeout=45, allow_agent=False, look_for_keys=False)
    return c


c = connect()
_, o, e = c.exec_command(
    "grep -E '^SITE_|^SITE_API' /opt/xbox-manager/.env | sed 's/=.*/=***/'",
    timeout=30,
)
print(o.read().decode())
print(e.read().decode()[:200])

# Check built server bundle contains store_url in calendario actions
_, o, e = c.exec_command(
    "CID=$(cd /opt/xbox-manager && docker compose ps -q app); "
    "docker exec \"$CID\" sh -c 'grep -R \"store_url\" /app/.next/server -l 2>/dev/null | head -10; "
    "grep -R \"store_url\" /app/src/app/calendario /app/src/components/calendario 2>/dev/null | head -20'",
    timeout=60,
)
print(o.read().decode())
print(e.read().decode()[:400])

# Simulate update for another game without store_url yet (id 9) via node inside container using same env
script = r'''
const fs = require("fs");
const env = Object.fromEntries(
  fs.readFileSync("/app/.env","utf8").split("\n").filter(l=>l&&!l.startsWith("#")&&l.includes("=")).map(l=>{
    const i=l.indexOf("="); return [l.slice(0,i).trim(), l.slice(i+1).trim()];
  })
);
const base = (env.SITE_BASE_URL || env.SITE_API_BASE_URL || "").replace(/\/api\/?$/i,"").replace(/\/+$/,"");
const key = env.SITE_API_KEY;
console.log("base", base);
console.log("keylen", (key||"").length);
(async () => {
  const get = await fetch(base + "/api/calendar/gamepass/9", { headers: { Authorization: "Bearer " + key } });
  const text = await get.text();
  console.log("GET", get.status, text.slice(0,200));
  const item = JSON.parse(text).item;
  const payload = {
    title: item.title,
    slug: item.slug,
    cover_url: item.cover_url,
    store_url: "https://www.xbox.com/pt-BR/games/store/gears-of-war-e-day/9PP8J9D7R9T9",
    release_at: item.release_at,
    body: item.body,
    on_gamepass: !!item.on_gamepass,
    on_ultimate: !!item.on_ultimate,
    on_premium: !!item.on_premium,
    on_essential: !!item.on_essential,
    on_pc: !!item.on_pc,
    platform_console: !!item.platform_console,
    platform_pc: !!item.platform_pc,
    platform_cloud: !!item.platform_cloud,
    status: item.status,
    sort_order: item.sort_order || 0,
  };
  const post = await fetch(base + "/api/calendar/gamepass/9", {
    method: "POST",
    headers: { Authorization: "Bearer " + key, "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  console.log("POST", post.status, await post.text());
})().catch(e => { console.error(e); process.exit(1); });
'''

sftp = c.open_sftp()
with sftp.file("/tmp/test-store.mjs", "w") as f:
    f.write(script)
sftp.close()

_, o, e = c.exec_command(
    "CID=$(cd /opt/xbox-manager && docker compose ps -q app); "
    "docker cp /tmp/test-store.mjs \"$CID\":/tmp/test-store.mjs; "
    "docker exec \"$CID\" node /tmp/test-store.mjs",
    timeout=60,
)
print(o.read().decode())
print(e.read().decode()[:800])
c.close()
