#!/usr/bin/env python3
from pathlib import Path
import paramiko

MANAGER_ROOT = Path("/mnt/c/Projetos/Xbox Manager")
MANAGER_KEY = Path("/mnt/c/Projetos/oracle/ssh-key-2026-06-11.key")
MANAGER_REMOTE = "/opt/xbox-manager"
FILES = ["src/components/calendario/GamePassPanel.tsx"]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
try:
    pkey = paramiko.Ed25519Key.from_private_key_file(str(MANAGER_KEY))
except Exception:
    pkey = paramiko.RSAKey.from_private_key_file(str(MANAGER_KEY))
c.connect("147.15.79.91", username="ubuntu", pkey=pkey, timeout=45, allow_agent=False, look_for_keys=False)
sftp = c.open_sftp()
for rel in FILES:
    print("put", rel)
    sftp.put(str(MANAGER_ROOT / rel), f"{MANAGER_REMOTE}/{rel}")
sftp.close()
_, o, e = c.exec_command(
    "cd /opt/xbox-manager && docker compose build app && docker compose up -d app && sleep 8 && curl -sS -o /dev/null -w 'health:%{http_code}\\n' http://127.0.0.1:3000/api/health",
    timeout=1200,
)
print(o.read().decode(errors="replace")[-2000:])
err = e.read().decode(errors="replace")
if err.strip():
    print("ERR:", err[-1500:])
print("exit", o.channel.recv_exit_status())
c.close()
