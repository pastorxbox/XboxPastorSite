#!/usr/bin/env python3
from pathlib import Path
import paramiko

KEY = Path("/mnt/c/Projetos/oracle/ssh-key-2026-06-11.key")
MODEL = "nvidia/nemotron-3-super-120b-a12b:free"
FALLBACK = "deepseek/deepseek-v4-flash:free"

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    pkey = paramiko.Ed25519Key.from_private_key_file(str(KEY))
except Exception:
    pkey = paramiko.RSAKey.from_private_key_file(str(KEY))
c.connect("147.15.79.91", username="ubuntu", pkey=pkey, timeout=45, allow_agent=False, look_for_keys=False)


def run(cmd, timeout=90):
    print(">>>", cmd[:180])
    _, o, e = c.exec_command(cmd, timeout=timeout)
    out = o.read().decode(errors="replace")
    err = e.read().decode(errors="replace")
    code = o.channel.recv_exit_status()
    if out.strip():
        print(out.rstrip()[:3000])
    if err.strip():
        print("ERR:", err[:800])
    print("exit=", code)
    return code, out


run("ls /opt/xbox-manager && docker ps --format '{{.Names}}' | head")
run("grep -E '^(POSTGRES_|DATABASE_URL=)' /opt/xbox-manager/.env | sed 's/:[^@/]*@/:***@/; s/PASSWORD=.*/PASSWORD=***/'")

# Discover DB user from compose
run("grep -E 'POSTGRES_USER|POSTGRES_DB|POSTGRES_PASSWORD' /opt/xbox-manager/docker-compose.yml /opt/xbox-manager/.env 2>/dev/null | sed 's/PASSWORD=.*/PASSWORD=***/' | head -20")

patch = f'''
const path = require("path");
process.chdir("/app");
const {{ PrismaClient }} = require("@prisma/client");
const prisma = new PrismaClient();
const MODEL = {MODEL!r};
const FALLBACK = {FALLBACK!r};
(async () => {{
  const row = await prisma.apiSetting.findUnique({{ where: {{ id: "default" }} }});
  const raw = row?.openRouterJson && typeof row.openRouterJson === "object" ? {{ ...row.openRouterJson }} : {{}};
  console.log("KEYS", Object.keys(raw));
  const social = raw.social && typeof raw.social === "object" ? {{ ...raw.social }} : {{}};
  console.log("BEFORE", JSON.stringify(social, null, 2));
  const primary = social.primary && typeof social.primary === "object" ? {{ ...social.primary }} : {{ retries: 2 }};
  primary.model = MODEL;
  primary.retries = Number(primary.retries) > 0 ? Number(primary.retries) : 2;
  let fallbacks = Array.isArray(social.fallbacks) ? social.fallbacks.map((f) => ({{ ...f }})) : [];
  while (fallbacks.length < 2) fallbacks.push({{ model: "", retries: 1 }});
  if (!String(fallbacks[0].model || "").trim()) {{
    fallbacks[0] = {{ model: FALLBACK, retries: Number(fallbacks[0].retries) || 1 }};
  }}
  social.primary = primary;
  social.fallbacks = fallbacks;
  social.apiKeyId = social.apiKeyId || "env";
  const next = {{ ...raw, social }};
  await prisma.apiSetting.upsert({{
    where: {{ id: "default" }},
    create: {{ id: "default", apiKey: process.env.API_KEY || "pending", openRouterJson: next }},
    update: {{ openRouterJson: next }},
  }});
  console.log("AFTER", JSON.stringify(social, null, 2));
  await prisma.$disconnect();
}})().catch((e) => {{ console.error(e); process.exit(1); }});
'''

sftp = c.open_sftp()
with sftp.file("/tmp/patch-social-model.js", "w") as f:
    f.write(patch)
# also upload OpenRouterAiPanel
local_panel = Path("/mnt/c/Projetos/Xbox Manager/src/components/settings/OpenRouterAiPanel.tsx")
sftp.put(str(local_panel), "/opt/xbox-manager/src/components/settings/OpenRouterAiPanel.tsx")
sftp.close()

run("docker cp /tmp/patch-social-model.js xbox-manager-app-1:/tmp/patch-social-model.js")
run("docker compose -f /opt/xbox-manager/docker-compose.yml exec -T -w /app app node /tmp/patch-social-model.js")

# rebuild app for UI label (optional but good)
run(
    "cd /opt/xbox-manager && docker compose build app && docker compose up -d app && sleep 8 && curl -sS -o /dev/null -w 'health:%{http_code}\\n' http://127.0.0.1:3000/api/health",
    timeout=1200,
)

c.close()
print("DONE")
