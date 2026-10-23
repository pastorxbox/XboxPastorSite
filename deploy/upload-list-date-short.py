#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
files = [
    ("public/includes/helpers.php", f"{REMOTE}/includes/helpers.php"),
    ("public/assets/css/vitrine.css", f"{REMOTE}/assets/css/vitrine.css"),
    ("public/includes/vitrine-layout.php", f"{REMOTE}/includes/vitrine-layout.php"),
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
import urllib.request, re
req = urllib.request.Request("https://pastorxbox.com.br/calendario?lang=pt", headers={"User-Agent":"Mozilla"})
h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8","replace")
print("asset", "20260917listmeta2" in h)
# sample arrive labels like 24/Set without year
arrives = re.findall(r'cal-card__arrive[^>]*>[^<]*📅\s*([0-9]{2}/[A-Za-zÀ-ú]{3}(?:/[0-9]{2})?)', h)
print("samples", arrives[:5])
print("has year format", any("/" in a and a.count("/")==2 for a in arrives))
print("day month only", all(a.count("/")==1 for a in arrives) if arrives else False)
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_date_short.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_date_short.py", timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
