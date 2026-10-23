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
import urllib.request
UA = {"User-Agent": "Mozilla"}

def has_carousel(url):
    req = urllib.request.Request(url, headers=UA)
    h = urllib.request.urlopen(req, timeout=45).read().decode("utf-8", "replace")
    return "news-carousel" in h, "home-featured" in h

for label, url in [
    ("home", "https://pastorxbox.com.br/?lang=pt"),
    ("noticias", "https://pastorxbox.com.br/noticias?lang=pt"),
    ("artigos", "https://pastorxbox.com.br/artigos?lang=pt"),
]:
    car, feat = has_carousel(url)
    print(label, "carousel=", car, "featured=", feat)
'''
sftp = c.open_sftp()
with sftp.file("/tmp/smoke_carousel_home_only.py", "w") as f:
    f.write(script)
sftp.close()
_, o, e = c.exec_command("python3 /tmp/smoke_carousel_home_only.py", timeout=90)
print(o.read().decode(errors="replace"))
print(e.read().decode(errors="replace"))
c.close()
print("DONE")
