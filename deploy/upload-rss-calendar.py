#!/usr/bin/env python3
"""Upload calendar RSS feed and footer links to Hostinger."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/includes/feed.php", f"{REMOTE}/includes/feed.php"),
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/rss.php", f"{REMOTE}/rss.php"),
    ("public/rss-calendar.php", f"{REMOTE}/rss-calendar.php"),
    ("public/.htaccess", f"{REMOTE}/.htaccess"),
]

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
    path = ROOT / local
    print(f"put {path.name} -> {remote}")
    sftp.put(str(path), remote)
sftp.close()

checks = r"""
set -e
curl -sS -o /tmp/rss-news.xml -w 'news:%{http_code}\n' -A Mozilla 'https://pastorxbox.com.br/rss.xml'
curl -sS -o /tmp/rss-cal.xml -w 'cal:%{http_code}\n' -A Mozilla 'https://pastorxbox.com.br/calendario/rss.xml'
echo '--- news title ---'
grep -o '<title>[^<]*</title>' /tmp/rss-news.xml | head -3
echo '--- cal title ---'
grep -o '<title>[^<]*</title>' /tmp/rss-cal.xml | head -5
echo '--- footer ---'
curl -sL -A Mozilla 'https://pastorxbox.com.br/' | grep -oE 'footer-rss|/rss\.xml|/calendario/rss\.xml|RSS Notícias|Notícias</a>|Calendário</a>' | head -20
"""
_, o, e = c.exec_command(checks, timeout=60)
print(o.read().decode(errors="replace"))
err = e.read().decode(errors="replace")
if err.strip():
    print("STDERR:", err[:600])
c.close()
print("DONE")
