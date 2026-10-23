#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
files = [
    ("public/includes/content.php", f"{REMOTE}/includes/content.php"),
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
import urllib.request
req = urllib.request.Request("https://pastorxbox.com.br/?lang=pt", headers={"User-Agent":"Mozilla"})
h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8","replace")
print("asset", "20260916mlist7" in h)
print("icon class", h.count("card-heading__icon"))
print("news icon", h.count("lucide--nav-news"))
print("articles icon", h.count("lucide--nav-articles"))
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_secicon.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_secicon.py", timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
