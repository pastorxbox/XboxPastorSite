#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
files = [
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
css = open("/home/u285071835/domains/pastorxbox.com.br/public_html/assets/css/vitrine.css").read()
print("stacked meta", "flex-direction: column" in css and "cal-card__meta" in css)
print("asset file", "20260917listmeta1" in open("/home/u285071835/domains/pastorxbox.com.br/public_html/includes/vitrine-layout.php").read())
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_list_meta.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_list_meta.py", timeout=45)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
