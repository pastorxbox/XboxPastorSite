#!/usr/bin/env python3
"""Sync TV Xbox expires_at Manager files to Oracle and rebuild."""
from pathlib import Path

import paramiko


def _first_existing(*candidates: Path) -> Path:
    for path in candidates:
        if path.is_file() or path.is_dir():
            return path
    return candidates[0]


KEY = _first_existing(
    Path(r"C:\Projetos\oracle\ssh-key-2026-06-11.key"),
    Path("/mnt/c/Projetos/oracle/ssh-key-2026-06-11.key"),
)
MANAGER_ROOT = _first_existing(
    Path(r"C:\Projetos\Xbox Manager"),
    Path("/mnt/c/Projetos/Xbox Manager"),
)
REMOTE_ROOT = "/opt/xbox-manager"
FILES = [
    "src/lib/site-api.ts",
    "src/app/tv-xbox/actions.ts",
    "src/components/tv-xbox/TvXboxManager.tsx",
]


def connect():
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    try:
        pkey = paramiko.Ed25519Key.from_private_key_file(str(KEY))
    except Exception:
        pkey = paramiko.RSAKey.from_private_key_file(str(KEY))
    c.connect(
        "147.15.79.91",
        username="ubuntu",
        pkey=pkey,
        timeout=45,
        allow_agent=False,
        look_for_keys=False,
    )
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
sftp = c.open_sftp()
for rel in FILES:
    local = MANAGER_ROOT / rel
    remote = f"{REMOTE_ROOT}/{rel}"
    if not local.is_file():
        raise SystemExit(f"missing {local}")
    print(f"put {rel}")
    sftp.put(str(local), remote)
sftp.close()

run(
    c,
    "grep -n expires_at /opt/xbox-manager/src/lib/site-api.ts "
    "/opt/xbox-manager/src/app/tv-xbox/actions.ts "
    "/opt/xbox-manager/src/components/tv-xbox/TvXboxManager.tsx | head -40",
)
code, _ = run(
    c,
    "cd /opt/xbox-manager && docker compose build app && docker compose up -d app "
    "&& sleep 8 && curl -sS -o /dev/null -w 'health:%{http_code}\\n' http://127.0.0.1:3000/api/health",
    timeout=1200,
)
c.close()
raise SystemExit(code)
