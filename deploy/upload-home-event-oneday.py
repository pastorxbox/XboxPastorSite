#!/usr/bin/env python3
"""Deploy: events only on start day in carousel (no multi-day below)."""
import os
from pathlib import Path

import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]

files = [
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
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
    print("put", local)
    sftp.put(str(ROOT / local), remote)
sftp.close()

script = r'''
import urllib.request, re
req = urllib.request.Request("https://pastorxbox.com.br/?lang=pt", headers={"User-Agent":"Mozilla"})
h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8","replace")
print("asset", "20260916evcar2" in h)
print("below", "home-events-below" in h)
print("event_titles", re.findall(r"Hoje é Dia de[^<]+", h))
print("event_pills", h.count("cat--events"))
print("carousel_slides", h.count("news-carousel__slide"))
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_ev_oneday.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_ev_oneday.py", timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
