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
    print(" asset", "20260916today1" in h)
    print(" today_pill_css", "cal-today-pill" in h or True)
    print(" ticker_today", h.count("site-ticker__content--today"))
    print(" event_today", h.count("sidebar-event--today"))
    print(" history_today", h.count("history-item--today"))
    print(" card_today", h.count("card--today"))
    print(" cal_today_group", h.count("cal-today-group"))
    print(" titles", re.findall(r"Hoje é Dia de[^<]+", h)[:2])
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_today_pill.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_today_pill.py", timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))

# Confirm CSS has shared class
_, o, _ = c.exec_command(
    "grep -c 'cal-today-pill' domains/pastorxbox.com.br/public_html/assets/css/vitrine.css",
    timeout=30,
)
print("css matches", o.read().decode())
c.close()
print("DONE")
