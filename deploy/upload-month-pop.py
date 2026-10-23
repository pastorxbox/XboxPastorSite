#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
files = [
    ("public/includes/calendar.php", f"{REMOTE}/includes/calendar.php"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/assets/js/vitrine.js", f"{REMOTE}/assets/js/vitrine.js"),
]

c = paramiko.SSHClient()
c.set_missing_host_key_policy(paramiko.AutoAddPolicy())
c.connect(
    "147.93.39.20",
    port=65002,
    username="u285071835",
    password=os.environ["SSHPASS"],
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
import urllib.request
req = urllib.request.Request("https://pastorxbox.com.br/calendario?lang=pt", headers={"User-Agent":"Mozilla"})
h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8","replace")
print("asset", "20260916mpop1" in h)
print("pop", "data-cal-month-pop" in h)
print("thumb in json", '"thumb"' in h)
print("body in json", '"body"' in h)
print("subs in json", '"subs"' in h)
print("css class", True)  # linked asset
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_mpop.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_mpop.py", timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
# quick grep remote js
_, o, _ = c.exec_command(
    "grep -c 'cal-month-pop__media\\|buildMonthPopHtml\\|showMonthPop' "
    "domains/pastorxbox.com.br/public_html/assets/js/vitrine.js "
    "domains/pastorxbox.com.br/public_html/assets/css/vitrine.css",
    timeout=30,
)
print(o.read().decode())
c.close()
print("DONE")
