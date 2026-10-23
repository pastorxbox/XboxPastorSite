#!/usr/bin/env python3
"""Check Oracle Manager for store_url support; optionally sync calendar files and rebuild."""
from __future__ import annotations

import os
import sys
from pathlib import Path

import paramiko

KEY = Path("/mnt/c/Projetos/oracle/ssh-key-2026-06-11.key")
HOST = "147.15.79.91"
USER = "ubuntu"
MANAGER_ROOT = Path("/mnt/c/Projetos/Xbox Manager")
REMOTE_ROOT = "/opt/xbox-manager"

FILES = [
    "src/app/calendario/actions.ts",
    "src/lib/site-api.ts",
    "src/components/calendario/GamePassPanel.tsx",
]


def connect() -> paramiko.SSHClient:
    c = paramiko.SSHClient()
    c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    pkey = paramiko.RSAKey.from_private_key_file(str(KEY))
    try:
        c.connect(HOST, username=USER, pkey=pkey, timeout=45, allow_agent=False, look_for_keys=False)
    except Exception:
        pkey = paramiko.Ed25519Key.from_private_key_file(str(KEY))
        c.connect(HOST, username=USER, pkey=pkey, timeout=45, allow_agent=False, look_for_keys=False)
    return c


def run(c: paramiko.SSHClient, cmd: str, timeout: int = 120) -> str:
    print(f">>> {cmd[:160]}")
    _, o, e = c.exec_command(cmd, timeout=timeout)
    out = o.read().decode(errors="replace")
    err = e.read().decode(errors="replace")
    code = o.channel.recv_exit_status()
    if out.strip():
        print(out.rstrip())
    if err.strip():
        print("ERR:", err[:800])
    if code != 0:
        print(f"exit={code}")
    return out


def main() -> int:
    mode = sys.argv[1] if len(sys.argv) > 1 else "check"
    c = connect()
    run(c, "ls -la /opt/xbox-manager | head -25")
    run(c, "cd /opt/xbox-manager && docker compose ps")
    run(
        c,
        "grep -rn store_url /opt/xbox-manager/src/app/calendario /opt/xbox-manager/src/lib/site-api.ts 2>/dev/null | head -20 || echo NO_HOST_MATCH",
    )

    if mode == "check":
        c.close()
        return 0

    # deploy files
    sftp = c.open_sftp()
    for rel in FILES:
        local = MANAGER_ROOT / rel
        remote = f"{REMOTE_ROOT}/{rel}"
        print(f"put {local} -> {remote}")
        # ensure remote dir
        parts = remote.split("/")[:-1]
        path = ""
        for p in parts:
            if not p:
                continue
            path += "/" + p
            try:
                sftp.stat(path)
            except OSError:
                try:
                    sftp.mkdir(path)
                except OSError:
                    pass
        sftp.put(str(local), remote)
    sftp.close()

    run(
        c,
        "cd /opt/xbox-manager && docker compose build app && docker compose up -d app",
        timeout=900,
    )
    run(
        c,
        "grep -n store_url /opt/xbox-manager/src/app/calendario/actions.ts /opt/xbox-manager/src/components/calendario/GamePassPanel.tsx | head -20",
    )
    run(c, "curl -sS -o /dev/null -w '%{http_code}\\n' http://127.0.0.1:3000/api/health || true")
    c.close()
    print("DONE")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
