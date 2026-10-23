#!/usr/bin/env python3
"""Slide "Chegando Hoje" no carrossel da Home + endpoint /api/settings/home-today.

--check: só baixa os ficheiros atuais do servidor e mostra o diff com os locais.
Sem flag: sobe como *.new, roda `php -l` nos PHP e só então substitui.
"""
import difflib
import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
ASSET_VERSION = "20261009hometoday1"

files = [
    ("public/includes/home-today.php", f"{REMOTE}/includes/home-today.php"),
    ("public/includes/api-home-today.php", f"{REMOTE}/includes/api-home-today.php"),
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/index.php", f"{REMOTE}/index.php"),
    ("public/api/index.php", f"{REMOTE}/api/index.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/.htaccess", f"{REMOTE}/.htaccess"),
]


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
    password=load_sshpass(),
    allow_agent=False,
    look_for_keys=False,
    timeout=45,
)
sftp = c.open_sftp()

if "--check" in sys.argv:
    for local, remote in files:
        try:
            with sftp.open(remote, "r") as fh:
                remote_text = fh.read().decode("utf-8", errors="replace")
        except OSError:
            print(f"=== {local}: (não existe no servidor)")
            continue
        local_text = (ROOT / local).read_text(encoding="utf-8")
        diff = list(
            difflib.unified_diff(
                remote_text.splitlines(),
                local_text.splitlines(),
                f"remote/{local}",
                f"local/{local}",
                lineterm="",
                n=1,
            )
        )
        print(f"=== {local}: {'igual' if not diff else f'{len(diff)} linhas de diff'}")
        for line in diff:
            print(line)
    sftp.close()
    c.close()
    sys.exit(0)

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
    f"| grep -oE '{ASSET_VERSION}|news-carousel|cat--calendar' | sort | uniq -c",
)
print("SMOKE HOME:")
print(out)
code, out, _ = run(
    c,
    "curl -s -o /dev/null -w '%{http_code}' 'https://pastorxbox.com.br/api/settings/home-today'",
)
print("SMOKE API (sem token, esperado 401):", out)
c.close()
print("DONE")
