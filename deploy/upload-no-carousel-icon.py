#!/usr/bin/env python3
import os
from pathlib import Path
import paramiko

ROOT = Path(__file__).resolve().parents[1]
REMOTE = "domains/pastorxbox.com.br/public_html"
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
sftp.put(str(ROOT / "public/includes/content.php"), f"{REMOTE}/includes/content.php")
sftp.close()
script = r'''
import urllib.request, re
req = urllib.request.Request("https://pastorxbox.com.br/?lang=pt", headers={"User-Agent":"Mozilla"})
h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8", "replace")
m = re.search(r"news-carousel.*?class=\"post-grid\"", h, re.S)
block = m.group(0) if m else ""
print("carousel icons", block.count("card-heading__icon"))
print("page icons", h.count("card-heading__icon"))
print("carousel slides", block.count("news-carousel__slide"))
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_no_carousel_icon.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_no_carousel_icon.py", timeout=60)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
