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

_, o, _ = c.exec_command(
    "CID=$(cd /opt/xbox-manager && docker compose ps -q app); "
    "docker exec \"$CID\" sh -c 'grep -R \"store_url\" /app/.next/server --include=\"*.js\" -l | head -30; "
    "echo ---; grep -R \"store_url\" /app/.next/server/chunks --include=\"*.js\" | head -5; "
    "echo ---; ls /app/.env 2>/dev/null; ls /app/.next/standalone/.env 2>/dev/null'",
    timeout=90,
)
print(o.read().decode(errors="replace"))

script = r'''
const fs = require("fs");
const envTxt = fs.readFileSync("/app/.env", "utf8");
const env = {};
for (const line of envTxt.split("\n")) {
  if (!line || line.startsWith("#") || !line.includes("=")) continue;
  const i = line.indexOf("=");
  env[line.slice(0, i).trim()] = line.slice(i + 1).trim();
}
const base = (env.SITE_BASE_URL || env.SITE_API_BASE_URL || "").replace(/\/api\/?$/i, "").replace(/\/+$/, "");
const key = env.SITE_API_KEY;
console.log("base", base);
(async () => {
  const get = await fetch(base + "/api/calendar/gamepass/9", { headers: { Authorization: "Bearer " + key } });
  const text = await get.text();
  console.log("GET", get.status);
  const item = JSON.parse(text).item;
  console.log("before store_url", item.store_url);
  const payload = { ...item, store_url: "https://www.xbox.com/pt-BR/games/store/gears-of-war-e-day/9N084N8M5T6B" };
  delete payload.id; delete payload.created_at; delete payload.updated_at;
  // coerce flags
  for (const k of ["on_gamepass","on_ultimate","on_premium","on_essential","on_pc","platform_console","platform_pc","platform_cloud"]) {
    payload[k] = !!payload[k];
  }
  const post = await fetch(base + "/api/calendar/gamepass/9", {
    method: "POST",
    headers: { Authorization: "Bearer " + key, "Content-Type": "application/json" },
    body: JSON.stringify(payload),
  });
  console.log("POST", post.status, await post.text());
  const get2 = await fetch(base + "/api/calendar/gamepass/9", { headers: { Authorization: "Bearer " + key } });
  const item2 = JSON.parse(await get2.text()).item;
  console.log("after store_url", item2.store_url);
})().catch((e) => { console.error(e); process.exit(1); });
'''
sftp = c.open_sftp()
with sftp.file("/tmp/test-store.cjs", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command(
    "CID=$(cd /opt/xbox-manager && docker compose ps -q app); "
    "docker cp /tmp/test-store.cjs \"$CID\":/tmp/test-store.cjs; "
    "docker exec \"$CID\" node /tmp/test-store.cjs",
    timeout=60,
)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace")[:800])
c.close()
