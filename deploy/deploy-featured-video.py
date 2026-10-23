#!/usr/bin/env python3
"""Vídeo destacado (YouTube) nos posts: migração 020 + envio dos ficheiros para a Hostinger.

Os PHP sobem como *.new, passam por `php -l` no servidor e só então substituem os atuais.
"""
import os
import sys
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
SQL_REMOTE = "domains/pastorxbox.com.br/sql/020_posts_featured_video.sql"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/includes/helpers.php", f"{REMOTE}/includes/helpers.php"),
    ("public/includes/posts.php", f"{REMOTE}/includes/posts.php"),
    ("public/includes/api-posts.php", f"{REMOTE}/includes/api-posts.php"),
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
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

try:
    sftp.stat("domains/pastorxbox.com.br/sql")
except OSError:
    sftp.mkdir("domains/pastorxbox.com.br/sql")
sftp.put(str(ROOT / "sql/020_posts_featured_video.sql"), SQL_REMOTE)

migrate = r"""
set -e
export $(grep -E '^(DB_HOST|DB_NAME|DB_USER|DB_PASS|DB_DATABASE|DB_USERNAME|DB_PASSWORD)=' domains/pastorxbox.com.br/config/.env 2>/dev/null | xargs) || true
DBN="${DB_NAME:-$DB_DATABASE}"
DBU="${DB_USER:-$DB_USERNAME}"
DBP="${DB_PASS:-$DB_PASSWORD}"
DBH="${DB_HOST:-localhost}"
COL=$(mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" -N -e "SHOW COLUMNS FROM posts LIKE 'featured_video_url';" | wc -l)
if [ "$COL" = "0" ]; then
  mysql -h "$DBH" -u "$DBU" -p"$DBP" "$DBN" < domains/pastorxbox.com.br/sql/020_posts_featured_video.sql
  echo "Column featured_video_url added"
else
  echo "Column featured_video_url already present"
fi
"""
code, out, err = run(c, migrate)
print(out.strip())
if code != 0:
    print("MIGRATION FAILED:", err[:800])
    sys.exit(1)

for local, remote in files:
    path = ROOT / local
    print(f"put {local} -> {remote}.new")
    sftp.put(str(path), f"{remote}.new")

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
    "| grep -oE 'news-carousel|data-carousel-video|20261008featuredvideo1' | sort | uniq -c",
)
print("SMOKE:")
print(out)
c.close()
print("DONE")
