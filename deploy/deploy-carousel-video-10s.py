#!/usr/bin/env python3
"""Carrossel da Home: vídeo destacado toca 10 s (30s→40s), também com prefers-reduced-motion.

Os ficheiros sobem como *.new, os PHP passam por `php -l` no servidor e só então substituem os atuais.
"""
import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"


def load_sshpass() -> str:
    value = os.environ.get("SSHPASS", "").strip()
    if value:
        return value
    env_file = ROOT / ".env"
    if env_file.exists():
        for line in env_file.read_text(encoding="utf-8").splitlines():
            key, sep, raw = line.partition("=")
            if sep and key.strip() == "SSHPASS":
                return raw.strip().strip('"').strip("'")
    raise SystemExit("Defina SSHPASS no ambiente ou no .env do site.")


PASSWORD = load_sshpass()
ASSET_VERSION = "20261009carouselvideo10s"

files = [
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
]


def run(client: paramiko.SSHClient, cmd: str, timeout: int = 60) -> tuple[int, str, str]:
    _, out, err = client.exec_command(cmd, timeout=timeout)
    code = out.channel.recv_exit_status()
    return code, out.read().decode(errors="replace"), err.read().decode(errors="replace")


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
sftp = c.open_sftp()

for local, remote in files:
    print(f"put {local} -> {remote}.new")
    sftp.put(str(ROOT / local), f"{remote}.new")

lint_failed = False
for local, remote in files:
    if not local.endswith(".php"):
        continue
    code, out, err = run(c, f"php -l {remote}.new")
    print(out.strip() or err.strip())
    if code != 0:
        lint_failed = True

if lint_failed:
    for _, remote in files:
        run(c, f"rm -f {remote}.new")
    print("ABORT: php -l falhou; nada foi substituído.")
    sys.exit(1)

for _, remote in files:
    run(c, f"mv -f {remote}.new {remote}")
sftp.close()

code, out, _ = run(
    c,
    "curl -sL -A Mozilla 'https://pastorxbox.com.br/' "
    f"| grep -oE 'start=30&amp;end=40|{ASSET_VERSION}' | sort | uniq -c",
)
print("SMOKE:")
print(out)
c.close()
print("DONE")
