#!/usr/bin/env python3
from pathlib import Path
import paramiko

KEY = Path("/mnt/c/Projetos/oracle/ssh-key-2026-06-11.key")
c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    pkey = paramiko.Ed25519Key.from_private_key_file(str(KEY))
except Exception:
    pkey = paramiko.RSAKey.from_private_key_file(str(KEY))
c.connect("147.15.79.91", username="ubuntu", pkey=pkey, timeout=45, allow_agent=False, look_for_keys=False)

_, o, e = c.exec_command(
    "CID=$(cd /opt/xbox-manager && docker compose ps -q app); "
    "docker exec \"$CID\" sh -c \"grep -R store_url /app/.next -n | head -50\"",
    timeout=90,
)
print("GREP:")
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace")[:300])

script = r'''
const fs = require("fs");
const env = {};
for (const line of fs.readFileSync("/tmp/manager.env", "utf8").split("\n")) {
  if (!line || line.startsWith("#") || !line.includes("=")) continue;
  const i = line.indexOf("=");
  env[line.slice(0, i).trim()] = line.slice(i + 1).trim();
}
const base = (env.SITE_BASE_URL || env.SITE_API_BASE_URL || "").replace(/\/api\/?$/i, "").replace(/\/+$/, "");
const key = env.SITE_API_KEY;
console.log("base", base);
(async () => {
  const id = 8;
  const get = await fetch(base + "/api/calendar/gamepass/" + id, { headers: { Authorization: "Bearer " + key } });
  const item = (await get.json()).item;
  console.log("before", item.store_url);
  const payload = {
    title: item.title,
    slug: item.slug,
    cover_url: item.cover_url,
    store_url: "https://www.xbox.com/pt-BR/games/store/runescape-dragonwilds/9NTESTSTORE01",
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
  const post = await fetch(base + "/api/calendar/gamepass/" + id, {
    method: "POST",
    headers: { Authorization: "Bearer " + key, "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  console.log("post", post.status, await post.text());
  const get2 = await fetch(base + "/api/calendar/gamepass/" + id, { headers: { Authorization: "Bearer " + key } });
  console.log("after", (await get2.json()).item.store_url);
})().catch((e) => { console.error(e); process.exit(1); });
'''
sftp = c.open_sftp()
with sftp.file("/tmp/test-store.cjs", "w") as f:
    f.write(script)
sftp.close()

_, o, e = c.exec_command(
    "CID=$(cd /opt/xbox-manager && docker compose ps -q app); "
    "docker cp /opt/xbox-manager/.env \"$CID\":/tmp/manager.env; "
    "docker cp /tmp/test-store.cjs \"$CID\":/tmp/test-store.cjs; "
    "docker exec \"$CID\" node /tmp/test-store.cjs",
    timeout=60,
)
print("TEST:")
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace")[:500])
c.close()
