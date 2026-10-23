#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
PASSWORD = os.environ["SSHPASS"]
files = [
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
    ("public/includes/i18n.php", f"{REMOTE}/includes/i18n.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect("147.93.39.20", port=65002, username="u285071835", password=PASSWORD,
          allow_agent=False, look_for_keys=False, timeout=45)
sftp = c.open_sftp()
for local, remote in files:
    print("put", local)
    sftp.put(str(ROOT / local), remote)
sftp.close()

script = r'''
import urllib.request, re
for path in ["/?lang=pt", "/calendario?lang=pt"]:
    req = urllib.request.Request("https://pastorxbox.com.br"+path, headers={"User-Agent":"Mozilla"})
    h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8","replace")
    print("==", path)
    print(" asset", "20260916hoje1" in h)
    print(" HOJE titles", re.findall(r"HOJE: [^<]{0,60}", h)[:5])
    print(" carousel phrase", re.findall(r"Hoje é Dia de[^<]+", h)[:2])
    print(" card--today", "card--today" in h)
    print(" cal-today-title", h.count("cal-today-title"))
    print(" cal-card--today", h.count("cal-card--today"))
    print(" sidebar-event--today", h.count("sidebar-event--today"))
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_hoje_title.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_hoje_title.py", timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
