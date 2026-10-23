#!/usr/bin/env python3
"""Inspect GamePassPanel on Oracle and rebuild app with local store_url fixes."""
from pathlib import Path

import paramiko

KEY = Path("/mnt/c/Projetos/oracle/ssh-key-2026-06-11.key")
MANAGER_ROOT = Path("/mnt/c/Projetos/Xbox Manager")
REMOTE_ROOT = "/opt/xbox-manager"
FILES = [
    "src/app/calendario/actions.ts",
    "src/lib/site-api.ts",
    "src/components/calendario/GamePassPanel.tsx",
]


def connect():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        pkey = paramiko.Ed25519Key.from_private_key_file(str(KEY))
    except Exception:
        pkey = paramiko.RSAKey.from_private_key_file(str(KEY))
    c.connect("147.15.79.91", username="ubuntu", pkey=pkey, timeout=45, allow_agent=False, look_for_keys=False)
    return c


def run(c, cmd, timeout=120):
    print(f">>> {cmd[:180]}")
    _, o, e = c.exec_command(cmd, timeout=timeout)
    out = o.read().decode(errors="replace")
    err = e.read().decode(errors="replace")
    code = o.channel.recv_exit_status()
    if out.strip():
        print(out.rstrip())
    if err.strip():
        print("ERR:", err[:1000])
    print(f"exit={code}")
    return code, out


c = connect()
run(c, "grep -n 'store_url\\|fd.set' /opt/xbox-manager/src/components/calendario/GamePassPanel.tsx | head -40")
run(c, "wc -l /opt/xbox-manager/src/components/calendario/GamePassPanel.tsx /opt/xbox-manager/src/app/calendario/actions.ts")

sftp = c.open_sftp()
for rel in FILES:
    local = MANAGER_ROOT / rel
    remote = f"{REMOTE_ROOT}/{rel}"
    print(f"put {rel}")
    sftp.put(str(local), remote)
sftp.close()

run(c, "grep -n 'store_url\\|fd.set' /opt/xbox-manager/src/components/calendario/GamePassPanel.tsx | head -40")
code, _ = run(
    c,
    "cd /opt/xbox-manager && docker compose build app && docker compose up -d app && sleep 8 && curl -sS -o /dev/null -w 'health:%{http_code}\\n' http://127.0.0.1:3000/api/health",
    timeout=1200,
)
c.close()
raise SystemExit(code)
